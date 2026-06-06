<?php

use App\Models\Category;
use App\Models\Command;
use App\Models\CommandView;
use App\Models\Community;
use App\Models\Document;
use App\Models\Favorite;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Models\User;
use Database\Seeders\CommunityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function assignDiscoveryRole(User $user, Community $community, string $roleName): void
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

/**
 * @return array{user: User, other_user: User, community: Community, other_community: Community, plugin: Plugin, other_plugin: Plugin, version: PluginVersion, document: Document, commands: array<string, Command>}
 */
function discoveryGraph(): array
{
    config([
        'scout.driver' => 'meilisearch',
        'scout.queue' => false,
        'scout.after_commit' => false,
    ]);

    $user = User::factory()->create(['email' => 'discovery-admin@example.test']);
    $otherUser = User::factory()->create(['email' => 'outsider@example.test']);
    $community = Community::factory()->create([
        'name' => 'Celem Ecosystem',
        'slug' => 'celem-ecosystem',
    ]);
    $otherCommunity = Community::factory()->create([
        'name' => 'Forge Operations',
        'slug' => 'forge-operations',
    ]);

    test()->seed(CommunityPermissionSeeder::class);
    assignDiscoveryRole($user, $community, 'community-admin');
    assignDiscoveryRole($otherUser, $otherCommunity, 'community-admin');

    return Command::withoutSyncingToSearch(function () use ($user, $otherUser, $community, $otherCommunity): array {
        $moderation = Category::factory()->create(['name' => 'Moderation', 'slug' => 'moderation']);
        $diagnostics = Category::factory()->create(['name' => 'Diagnostics', 'slug' => 'diagnostics']);

        $plugin = Plugin::factory()->create([
            'community_id' => $community->id,
            'name' => 'Celem Core',
            'slug' => 'celem-core',
        ]);
        $version = PluginVersion::factory()->latest()->create([
            'plugin_id' => $plugin->id,
            'version' => '1.0.0',
        ]);
        $document = Document::factory()->create([
            'plugin_version_id' => $version->id,
            'path' => 'docs/moderation.md',
            'title' => 'Moderation Commands',
        ]);

        $otherPlugin = Plugin::factory()->create([
            'community_id' => $community->id,
            'name' => 'Blood Economy',
            'slug' => 'blood-economy',
        ]);
        $otherVersion = PluginVersion::factory()->latest()->create([
            'plugin_id' => $otherPlugin->id,
            'version' => '2.0.0',
        ]);
        $otherDocument = Document::factory()->create([
            'plugin_version_id' => $otherVersion->id,
            'path' => 'docs/economy.md',
            'title' => 'Economy Commands',
        ]);

        $foreignPlugin = Plugin::factory()->create([
            'community_id' => $otherCommunity->id,
            'name' => 'Private Ops',
            'slug' => 'private-ops',
        ]);
        $foreignVersion = PluginVersion::factory()->latest()->create([
            'plugin_id' => $foreignPlugin->id,
            'version' => '9.0.0',
        ]);
        $foreignDocument = Document::factory()->create([
            'plugin_version_id' => $foreignVersion->id,
            'path' => 'docs/private.md',
            'title' => 'Private Commands',
        ]);

        $commands = [
            'ban-player' => Command::factory()->create([
                'plugin_version_id' => $version->id,
                'document_id' => $document->id,
                'category_id' => $moderation->id,
                'name' => 'Ban Player',
                'slug' => 'ban-player',
                'syntax' => '/ban <player>',
                'description' => 'Remove a player from the server with an audit trail.',
                'aliases' => ['/block'],
            ]),
            'kick-player' => Command::factory()->create([
                'plugin_version_id' => $version->id,
                'document_id' => $document->id,
                'category_id' => $moderation->id,
                'name' => 'Kick Player',
                'slug' => 'kick-player',
                'syntax' => '/kick <player>',
                'description' => 'Disconnect a player from the current shard.',
            ]),
            'inspect-balance' => Command::factory()->create([
                'plugin_version_id' => $otherVersion->id,
                'document_id' => $otherDocument->id,
                'category_id' => $diagnostics->id,
                'name' => 'Inspect Balance',
                'slug' => 'inspect-balance',
                'syntax' => '/balance inspect <player>',
                'description' => 'Inspect player wallet state.',
            ]),
            'secret-ban' => Command::factory()->create([
                'plugin_version_id' => $foreignVersion->id,
                'document_id' => $foreignDocument->id,
                'category_id' => $moderation->id,
                'name' => 'Secret Ban',
                'slug' => 'secret-ban',
                'syntax' => '/secret-ban <player>',
                'description' => 'Private command from another community.',
            ]),
        ];

        return [
            'user' => $user,
            'other_user' => $otherUser,
            'community' => $community,
            'other_community' => $otherCommunity,
            'plugin' => $plugin,
            'other_plugin' => $otherPlugin,
            'version' => $version,
            'document' => $document,
            'commands' => $commands,
        ];
    });
}

function importDiscoveryCommands(int $expectedDocuments): int
{
    Artisan::call('scout:sync-index-settings');

    $client = new Client((string) config('scout.meilisearch.host'), (string) config('scout.meilisearch.key'));
    $index = $client->index((new Command)->searchableAs());

    try {
        $task = $index->deleteAllDocuments();
        $taskUid = is_array($task) ? $task['taskUid'] ?? null : null;

        if (is_int($taskUid)) {
            $client->waitForTask($taskUid);
        }
    } catch (ApiException) {
        // The first test run creates the index during import.
    }

    Artisan::call('scout:import', ['model' => Command::class]);

    $documents = 0;

    retry(20, function () use ($index, $expectedDocuments, &$documents): void {
        $documents = (int) $index->stats()['numberOfDocuments'];

        expect($documents)->toBeGreaterThanOrEqual($expectedDocuments);
    }, 250);

    return $documents;
}

it('indexes commands in meilisearch through scout import', function (): void {
    discoveryGraph();

    expect(importDiscoveryCommands(4))->toBeGreaterThanOrEqual(4);
});

it('creates plugins for users with plugins manage permission', function (): void {
    $graph = discoveryGraph();
    $token = $graph['user']->createToken('plugin-create-test')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/plugins', [
            'community_id' => $graph['community']->id,
            'name' => 'Arena Rules',
            'slug' => 'arena-rules',
            'description' => 'Command docs for arena moderation workflows.',
            'github_repo' => 'commandsphere/arena-rules',
            'docs_path' => 'docs',
            'default_branch' => 'main',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.slug', 'arena-rules')
        ->assertJsonPath('data.community.slug', 'celem-ecosystem')
        ->assertJsonPath('data.repository_url', 'commandsphere/arena-rules')
        ->assertJsonPath('data.documentation_path', 'docs');

    $this->assertDatabaseHas('plugins', [
        'community_id' => $graph['community']->id,
        'slug' => 'arena-rules',
        'repository_url' => 'commandsphere/arena-rules',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);
});

it('rejects plugin creation without plugins manage permission', function (): void {
    $graph = discoveryGraph();
    $member = User::factory()->create(['email' => 'member-create@example.test']);
    assignDiscoveryRole($member, $graph['community'], 'member');
    $token = $member->createToken('plugin-create-test')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/plugins', [
            'community_id' => $graph['community']->id,
            'name' => 'Arena Rules',
            'slug' => 'arena-rules',
            'github_repo' => 'commandsphere/arena-rules',
            'docs_path' => 'docs',
            'default_branch' => 'main',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'authorization.denied');
});

it('validates plugin creation payloads', function (): void {
    $graph = discoveryGraph();
    $token = $graph['user']->createToken('plugin-create-test')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/plugins', [
            'community_id' => $graph['community']->id,
            'name' => '',
            'slug' => 'not allowed',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors(['name', 'slug', 'github_repo', 'docs_path', 'default_branch']);
});

it('searches commands with facets and plugin filters', function (): void {
    $graph = discoveryGraph();
    importDiscoveryCommands(4);

    $token = $graph['user']->createToken('search-test')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=player');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'ban-player')
        ->assertJsonPath('facets.community.celem-ecosystem', 3)
        ->assertJsonPath('facets.plugin.celem-core', 2)
        ->assertJsonPath('facets.category.moderation', 2);

    $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=player&plugin=blood-economy')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'inspect-balance');
});

it('filters search results by community and returns community facets', function (): void {
    $graph = discoveryGraph();
    assignDiscoveryRole($graph['user'], $graph['other_community'], 'community-admin');
    importDiscoveryCommands(4);

    $token = $graph['user']->createToken('search-test')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=ban')
        ->assertOk()
        ->assertJsonPath('facets.community.celem-ecosystem', 1)
        ->assertJsonPath('facets.community.forge-operations', 1);

    $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=ban&community=celem-ecosystem')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'ban-player')
        ->assertJsonMissingPath('facets.community.forge-operations');

    $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=ban&community='.$graph['other_community']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'secret-ban')
        ->assertJsonPath('facets.community.forge-operations', 1);
});

it('does not expose commands from communities outside the user scope', function (): void {
    $graph = discoveryGraph();
    importDiscoveryCommands(4);

    $token = $graph['user']->createToken('search-test')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/search?q=secret')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonMissingPath('facets.community.forge-operations');
});

it('creates lists and removes polymorphic favorites', function (): void {
    $graph = discoveryGraph();
    $token = $graph['user']->createToken('favorites-test')->plainTextToken;
    $command = $graph['commands']['ban-player'];

    $this
        ->withToken($token)
        ->postJson('/api/v1/favorites', [
            'type' => 'command',
            'id' => $command->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'command');

    expect(Favorite::query()->count())->toBe(1);

    $this
        ->withToken($token)
        ->getJson('/api/v1/favorites')
        ->assertOk()
        ->assertJsonPath('data.0.favoritable_id', $command->id);

    $this
        ->withToken($token)
        ->deleteJson('/api/v1/favorites', [
            'type' => 'command',
            'id' => $command->id,
        ])
        ->assertNoContent();

    expect(Favorite::query()->count())->toBe(0);
});

it('deduplicates command views inside the short analytics window', function (): void {
    $graph = discoveryGraph();
    $token = $graph['user']->createToken('views-test')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/commands/ban-player/view')
        ->assertCreated()
        ->assertJsonPath('data.recorded', true);

    $this
        ->withToken($token)
        ->postJson('/api/v1/commands/ban-player/view')
        ->assertOk()
        ->assertJsonPath('data.recorded', false);

    expect(CommandView::query()->where('command_id', $graph['commands']['ban-player']->id)->count())->toBe(1);
});

it('returns most viewed commands for communities with analytics permission', function (): void {
    $graph = discoveryGraph();
    $token = $graph['user']->createToken('analytics-test')->plainTextToken;

    CommandView::factory()->count(3)->create([
        'command_id' => $graph['commands']['ban-player']->id,
        'user_id' => $graph['user']->id,
        'viewed_at' => now()->subMinutes(20),
    ]);
    CommandView::factory()->create([
        'command_id' => $graph['commands']['kick-player']->id,
        'user_id' => $graph['user']->id,
        'viewed_at' => now()->subMinutes(15),
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/v1/analytics/most-viewed?community=celem-ecosystem')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'ban-player')
        ->assertJsonPath('data.0.views', 3);
});
