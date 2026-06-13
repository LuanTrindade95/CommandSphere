<?php

use App\Contracts\GitHubClient;
use App\Data\GitHubMarkdownFile;
use App\Models\Community;
use App\Models\IngestionRun;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Models\User;
use Database\Seeders\CommunityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function assignAutomationRole(User $user, Community $community, string $roleName): void
{
    $community->users()->syncWithoutDetaching([
        $user->id => ['role' => $roleName],
    ]);

    $previousCommunityId = getPermissionsTeamId();
    setPermissionsTeamId($community->id);

    try {
        $role = Role::query()
            ->where('community_id', $community->id)
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->firstOrFail();

        $user->assignRole($role);
    } finally {
        setPermissionsTeamId($previousCommunityId);
    }
}

function createAutomationPluginGraph(): array
{
    config([
        'queue.default' => 'sync',
        'scout.driver' => 'null',
        'broadcasting.default' => 'null',
    ]);

    $community = Community::factory()->create([
        'name' => 'Celem Ecosystem',
        'slug' => 'celem-ecosystem',
    ]);

    test()->seed(CommunityPermissionSeeder::class);

    $plugin = Plugin::factory()->create([
        'community_id' => $community->id,
        'name' => 'Webhook Plugin',
        'slug' => 'webhook-plugin',
        'repository_url' => 'commandsphere/webhook-plugin',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);
    $version = PluginVersion::factory()->latest()->create([
        'plugin_id' => $plugin->id,
        'version' => '1.0.0',
        'git_ref' => 'main',
    ]);
    $admin = User::factory()->create(['email' => 'automation-admin@example.test']);
    $member = User::factory()->create(['email' => 'automation-member@example.test']);
    $outsider = User::factory()->create(['email' => 'automation-outsider@example.test']);

    assignAutomationRole($admin, $community, 'community-admin');
    assignAutomationRole($member, $community, 'member');

    return compact('community', 'plugin', 'version', 'admin', 'member', 'outsider');
}

function automationMarkdownFile(bool $notModified = false): GitHubMarkdownFile
{
    return new GitHubMarkdownFile(
        path: 'docs/commands.md',
        content: $notModified ? null : <<<'MARKDOWN'
---
title: Automation Commands
commands:
  - name: Sync Status
    slug: sync-status
    syntax: /sync status
    description: Shows sync status.
    category: diagnostics
---

# Automation Commands
MARKDOWN,
        etag: '"automation-etag"',
        notModified: $notModified,
    );
}

function signedGitHubPayload(array $payload, string $secret): array
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'json' => $json,
        'signature' => 'sha256='.hash_hmac('sha256', $json, $secret),
    ];
}

it('dispatches ingestion from a github webhook with a valid signature', function (): void {
    createAutomationPluginGraph();
    config(['commandsphere.github.webhook_secret' => 'github-secret']);
    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            return [automationMarkdownFile()];
        }
    });

    $payload = signedGitHubPayload([
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'commandsphere/webhook-plugin'],
    ], 'github-secret');

    $this
        ->call(
            'POST',
            '/api/v1/webhooks/github',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $payload['signature'],
            ],
            content: $payload['json'],
        )
        ->assertAccepted()
        ->assertJsonPath('queued', 1);

    $run = IngestionRun::query()->firstOrFail();

    expect($run->status)->toBe('success')
        ->and($run->source)->toBe('webhook')
        ->and($run->stats['docs_parsed'])->toBe(1)
        ->and($run->stats['commands_extracted'])->toBe(1);
});

it('does not enqueue a duplicate ingestion while one is already active', function (): void {
    $graph = createAutomationPluginGraph();
    $existingRun = IngestionRun::factory()->create([
        'plugin_version_id' => $graph['version']->id,
        'source' => 'manual',
        'status' => 'running',
    ]);
    $token = $graph['admin']->createToken('sync-duplicate-test')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/plugins/'.$graph['plugin']->id.'/sync')
        ->assertOk()
        ->assertJsonPath('queued', false)
        ->assertJsonPath('ingestion_run.id', $existingRun->id);

    expect(IngestionRun::query()->count())->toBe(1);
});

it('rejects a github webhook with an invalid signature', function (): void {
    createAutomationPluginGraph();
    config(['commandsphere.github.webhook_secret' => 'github-secret']);

    $this
        ->call(
            'POST',
            '/api/v1/webhooks/github',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            ],
            content: json_encode([
                'ref' => 'refs/heads/main',
                'repository' => ['full_name' => 'commandsphere/webhook-plugin'],
            ], JSON_THROW_ON_ERROR),
        )
        ->assertUnauthorized()
        ->assertJsonPath('code', 'webhook.invalid_signature');

    expect(IngestionRun::query()->count())->toBe(0);
});

it('scheduled sync reuses etag and does not reprocess unchanged content', function (): void {
    createAutomationPluginGraph();

    $client = new class implements GitHubClient
    {
        public int $calls = 0;

        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            $this->calls++;

            return [automationMarkdownFile($this->calls > 1)];
        }
    };

    app()->instance(GitHubClient::class, $client);

    Artisan::call('commandsphere:sync-scheduled');
    Artisan::call('commandsphere:sync-scheduled');

    $runs = IngestionRun::query()->orderBy('id')->get();

    expect($runs)->toHaveCount(2)
        ->and($runs[0]->source)->toBe('scheduled')
        ->and($runs[1]->source)->toBe('scheduled')
        ->and($runs[0]->stats['docs_parsed'])->toBe(1)
        ->and($runs[1]->stats['docs_parsed'])->toBe(0)
        ->and($runs[1]->log[0]['code'])->toBe('document_not_modified');
});

it('lists ingestion runs through scoped paginated queries', function (): void {
    $graph = createAutomationPluginGraph();
    $foreignCommunity = Community::factory()->create();
    $foreignPlugin = Plugin::factory()->create(['community_id' => $foreignCommunity->id]);
    $foreignVersion = PluginVersion::factory()->create(['plugin_id' => $foreignPlugin->id]);
    $token = $graph['admin']->createToken('ingestion-index-test')->plainTextToken;

    IngestionRun::factory()->create([
        'plugin_version_id' => $graph['version']->id,
        'status' => 'success',
    ]);
    IngestionRun::factory()->create([
        'plugin_version_id' => $graph['version']->id,
        'status' => 'failed',
    ]);
    $foreignRun = IngestionRun::factory()->create([
        'plugin_version_id' => $foreignVersion->id,
        'status' => 'success',
    ]);

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/ingestions?per_page=1');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($foreignRun->id);
});

it('denies private community broadcast channel access without scoped permission', function (): void {
    $graph = createAutomationPluginGraph();
    $token = $graph['outsider']->createToken('broadcast-channel-test')->plainTextToken;

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-reverb-key',
        'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
        'broadcasting.connections.reverb.app_id' => 'test-reverb-app',
    ]);
    Broadcast::forgetDrivers();

    expect($graph['outsider']->communities()->count())->toBe(0)
        ->and($graph['outsider']->hasCommunityPermission($graph['community'], 'analytics.view'))->toBeFalse();

    $this
        ->withToken($token)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-community.'.$graph['community']->slug,
        ])
        ->assertForbidden();
});
