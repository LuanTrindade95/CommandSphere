<?php

use App\Models\Category;
use App\Models\Command;
use App\Models\Community;
use App\Models\Document;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

/**
 * Characterization tests for the optional-user resolution duplicated in
 * CatalogController::currentUser() and SearchController::currentUser()
 * (F-013). These tests lock in the CURRENT behavior before the C6 refactor
 * extracts a single resolver, so the refactor cannot change status codes or
 * response bodies for any public discovery endpoint.
 *
 * Scope groups produced by the current implementation:
 * - "public" (widest): no Authorization header, "Authorization: Basic ...",
 *   or "Authorization: Bearer " with an empty token. All of these fail to
 *   produce a non-empty bearer string, so currentUser() returns null and the
 *   request is treated as anonymous (DiscoveryAccess returns every
 *   community).
 * - "member" scope: a valid, non-expired token for a user who belongs to
 *   exactly one community.
 * - "empty" scope: an Authorization header that looks like a bearer attempt
 *   but does not resolve to a user (garbage token, revoked token). The
 *   guard clause returns `new User` instead of widening to public, so
 *   DiscoveryAccess sees a persisted-less user with zero memberships.
 * - "non-member" scope: a valid, non-expired token for a real persisted
 *   user with zero community memberships. Included to show it produces the
 *   same body as the "empty" group, even though it takes the
 *   `$tokenable instanceof User` branch instead of the `new User` fallback.
 */
uses(RefreshDatabase::class);

/**
 * @return array{
 *     community: Community,
 *     otherCommunity: Community,
 *     member: User,
 *     nonMember: User,
 *     plugin: Plugin,
 *     document: Document,
 *     command: Command,
 *     foreignPlugin: Plugin,
 *     foreignDocument: Document,
 *     foreignCommand: Command,
 * }
 */
function bearerResolutionGraph(): array
{
    config([
        'scout.driver' => 'meilisearch',
        'scout.queue' => false,
        'scout.after_commit' => false,
    ]);

    $community = Community::factory()->create([
        'name' => 'Celem Ecosystem Bearer',
        'slug' => 'celem-ecosystem-bearer',
    ]);
    $otherCommunity = Community::factory()->create([
        'name' => 'Forge Operations Bearer',
        'slug' => 'forge-operations-bearer',
    ]);

    $member = User::factory()->create(['email' => 'bearer-member@example.test']);
    $member->communities()->syncWithoutDetaching([
        $community->id => ['role' => 'member'],
    ]);

    $nonMember = User::factory()->create(['email' => 'bearer-nonmember@example.test']);

    [$plugin, $document, $command] = Command::withoutSyncingToSearch(function () use ($community): array {
        $plugin = Plugin::factory()->create([
            'community_id' => $community->id,
            'name' => 'Celem Core Bearer',
            'slug' => 'celem-core-bearer',
        ]);
        $version = PluginVersion::factory()->latest()->create([
            'plugin_id' => $plugin->id,
            'version' => '1.0.0',
        ]);
        $document = Document::factory()->create([
            'plugin_version_id' => $version->id,
            'path' => 'docs/widget-status.md',
            'title' => 'Widget Status Commands',
        ]);
        $command = Command::factory()->create([
            'plugin_version_id' => $version->id,
            'document_id' => $document->id,
            'category_id' => Category::factory()->create()->id,
            'name' => 'Widget Status',
            'slug' => 'widget-status',
            'syntax' => '/widget status',
            'description' => 'Reports widget status for the member community.',
        ]);

        return [$plugin, $document, $command];
    });

    [$foreignPlugin, $foreignDocument, $foreignCommand] = Command::withoutSyncingToSearch(function () use ($otherCommunity): array {
        $plugin = Plugin::factory()->create([
            'community_id' => $otherCommunity->id,
            'name' => 'Forge Ops Bearer',
            'slug' => 'forge-ops-bearer',
        ]);
        $version = PluginVersion::factory()->latest()->create([
            'plugin_id' => $plugin->id,
            'version' => '9.0.0',
        ]);
        $document = Document::factory()->create([
            'plugin_version_id' => $version->id,
            'path' => 'docs/secret-status.md',
            'title' => 'Secret Status Commands',
        ]);
        $command = Command::factory()->create([
            'plugin_version_id' => $version->id,
            'document_id' => $document->id,
            'category_id' => Category::factory()->create()->id,
            'name' => 'Secret Status',
            'slug' => 'secret-status',
            'syntax' => '/secret status',
            'description' => 'Reports secret status for the foreign community.',
        ]);

        return [$plugin, $document, $command];
    });

    return compact(
        'community',
        'otherCommunity',
        'member',
        'nonMember',
        'plugin',
        'document',
        'command',
        'foreignPlugin',
        'foreignDocument',
        'foreignCommand',
    );
}

function importBearerResolutionCommands(int $expectedDocuments): void
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

    retry(20, function () use ($index, $expectedDocuments): void {
        expect((int) $index->stats()['numberOfDocuments'])->toBeGreaterThanOrEqual($expectedDocuments);
    }, 250);
}

/**
 * @return array{headers: array<string, string>, group: string}
 */
function bearerCase(string $group, ?string $header = null): array
{
    return [
        'headers' => $header === null ? [] : ['Authorization' => $header],
        'group' => $group,
    ];
}

/**
 * @return array<string, array{headers: array<string, string>, group: string}>
 */
function bearerCases(array $graph): array
{
    $validToken = $graph['member']->createToken('bearer-resolution')->plainTextToken;
    $nonMemberToken = $graph['nonMember']->createToken('bearer-resolution')->plainTextToken;

    $revokedTokenModel = $graph['member']->createToken('bearer-resolution-revoked');
    $revokedToken = $revokedTokenModel->plainTextToken;
    $revokedTokenModel->accessToken->delete();

    return [
        'no header (anonymous)' => bearerCase('public'),
        'Authorization: Basic ...' => bearerCase('public', 'Basic '.base64_encode('user:pass')),
        'Authorization: Bearer  (empty token)' => bearerCase('public', 'Bearer '),
        'valid member token' => bearerCase('member', 'Bearer '.$validToken),
        'garbage bearer token' => bearerCase('empty', 'Bearer not-a-real-token'),
        'revoked bearer token' => bearerCase('empty', 'Bearer '.$revokedToken),
        'valid token, user has no membership' => bearerCase('empty', 'Bearer '.$nonMemberToken),
    ];
}

it('resolves the communities index to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $response = $this->withHeaders($case['headers'])->getJson('/api/v1/communities');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        match ($case['group']) {
            'public' => expect($slugs)->toEqualCanonicalizing([
                $graph['community']->slug,
                $graph['otherCommunity']->slug,
            ], "case [{$label}] should be public scope"),
            'member' => expect($slugs)->toEqualCanonicalizing([
                $graph['community']->slug,
            ], "case [{$label}] should be member scope"),
            'empty' => expect($slugs)->toBe([], "case [{$label}] should be empty scope"),
        };
    }
});

it('resolves a single community to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $ownResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/communities/'.$graph['community']->slug);
        $foreignResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/communities/'.$graph['otherCommunity']->slug);

        match ($case['group']) {
            'public' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertOk(),
            ],
            'member' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertForbidden()->assertJsonPath('code', 'authorization.denied'),
            ],
            'empty' => [
                $ownResponse->assertForbidden()->assertJsonPath('code', 'authorization.denied'),
                $foreignResponse->assertForbidden()->assertJsonPath('code', 'authorization.denied'),
            ],
        };
    }
});

it('resolves the plugins index to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $response = $this->withHeaders($case['headers'])->getJson('/api/v1/plugins');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        match ($case['group']) {
            'public' => expect($slugs)->toEqualCanonicalizing([
                $graph['plugin']->slug,
                $graph['foreignPlugin']->slug,
            ], "case [{$label}] should be public scope"),
            'member' => expect($slugs)->toEqualCanonicalizing([
                $graph['plugin']->slug,
            ], "case [{$label}] should be member scope"),
            'empty' => expect($slugs)->toBe([], "case [{$label}] should be empty scope"),
        };
    }
});

it('resolves a single plugin to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $ownResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/plugins/'.$graph['plugin']->slug);
        $foreignResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/plugins/'.$graph['foreignPlugin']->slug);

        match ($case['group']) {
            'public' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertOk(),
            ],
            'member' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertNotFound(),
            ],
            'empty' => [
                $ownResponse->assertNotFound(),
                $foreignResponse->assertNotFound(),
            ],
        };
    }
});

it('resolves plugin version documents to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();
    $version = $graph['plugin']->versions()->first();

    foreach (bearerCases($graph) as $label => $case) {
        $response = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/plugins/'.$graph['plugin']->slug.'/versions/'.$version->version.'/documents');

        match ($case['group']) {
            'public', 'member' => $response->assertOk(),
            'empty' => $response->assertNotFound(),
        };
    }
});

it('resolves a single document to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $ownResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/documents/'.$graph['document']->id);
        $foreignResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/documents/'.$graph['foreignDocument']->id);

        match ($case['group']) {
            'public' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertOk(),
            ],
            'member' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertNotFound(),
            ],
            'empty' => [
                $ownResponse->assertNotFound(),
                $foreignResponse->assertNotFound(),
            ],
        };
    }
});

it('resolves a single command to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();

    foreach (bearerCases($graph) as $label => $case) {
        $ownResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/commands/'.$graph['command']->slug);
        $foreignResponse = $this->withHeaders($case['headers'])
            ->getJson('/api/v1/commands/'.$graph['foreignCommand']->slug);

        match ($case['group']) {
            'public' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertOk(),
            ],
            'member' => [
                $ownResponse->assertOk(),
                $foreignResponse->assertNotFound(),
            ],
            'empty' => [
                $ownResponse->assertNotFound(),
                $foreignResponse->assertNotFound(),
            ],
        };
    }
});

it('resolves search results to the same scope before the refactor', function (): void {
    $graph = bearerResolutionGraph();
    importBearerResolutionCommands(2);

    foreach (bearerCases($graph) as $label => $case) {
        $response = $this->withHeaders($case['headers'])->getJson('/api/v1/search?q=status');
        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        match ($case['group']) {
            'public' => expect($slugs)->toEqualCanonicalizing([
                $graph['command']->slug,
                $graph['foreignCommand']->slug,
            ], "case [{$label}] should be public scope"),
            'member' => expect($slugs)->toEqualCanonicalizing([
                $graph['command']->slug,
            ], "case [{$label}] should be member scope"),
            'empty' => expect($slugs)->toBe([], "case [{$label}] should be empty scope"),
        };
    }
});

it('accepts an expired bearer token as its owner on public discovery endpoints (pre-existing gap, not fixed here)', function (): void {
    $graph = bearerResolutionGraph();

    // findToken() from laravel/sanctum only hashes and looks up the token
    // row; it does not check `expires_at`. That check lives in
    // Laravel\Sanctum\Guard, which only runs behind the `auth:sanctum`
    // middleware. CatalogController/SearchController::currentUser() call
    // PersonalAccessToken::findToken() directly, bypassing that guard, so
    // an expired token is currently still resolved to its owner here.
    $expiredToken = $graph['member']->createToken('expired', ['*'], now()->subDay())->plainTextToken;

    $this->withHeaders(['Authorization' => 'Bearer '.$expiredToken])
        ->getJson('/api/v1/plugins/'.$graph['plugin']->slug)
        ->assertOk();

    $this->withHeaders(['Authorization' => 'Bearer '.$expiredToken])
        ->getJson('/api/v1/plugins/'.$graph['foreignPlugin']->slug)
        ->assertNotFound();
});
