<?php

use App\Contracts\GitHubClient;
use App\Data\GitHubMarkdownFile;
use App\Events\CommandIndexUpdated;
use App\Events\IngestionRunStatusChanged;
use App\Exceptions\GitHubRateLimitException;
use App\Models\Community;
use App\Models\IngestionRun;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Services\Ingestion\IngestionService;
use App\Support\CorrelationId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'queue.default' => 'sync',
        'scout.driver' => 'null',
    ]);

    Event::fake([
        CommandIndexUpdated::class,
        IngestionRunStatusChanged::class,
    ]);

    // Start every test from a clean, real telemetry log so assertions read
    // only the lines this test produced.
    @unlink(storage_path('logs/telemetry.log'));
});

/**
 * @return list<array<string, mixed>>
 */
function readTelemetryEvents(): array
{
    $path = storage_path('logs/telemetry.log');

    if (! file_exists($path)) {
        return [];
    }

    $lines = array_filter(explode("\n", trim(file_get_contents($path) ?: '')));

    return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function telemetryEventsNamed(string $name): array
{
    return array_values(array_filter(
        readTelemetryEvents(),
        fn (array $entry): bool => ($entry['message'] ?? null) === $name,
    ));
}

function telemetryPluginVersion(): PluginVersion
{
    $community = Community::factory()->create();
    $plugin = Plugin::factory()->create([
        'community_id' => $community->id,
        'name' => 'Telemetry Plugin',
        'slug' => 'telemetry-plugin',
        'repository_url' => 'commandsphere/telemetry-plugin',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);

    return PluginVersion::factory()->latest()->create([
        'plugin_id' => $plugin->id,
        'version' => '1.0.0',
        'git_ref' => 'main',
    ]);
}

function telemetryMarkdownFile(): GitHubMarkdownFile
{
    return new GitHubMarkdownFile(
        path: 'docs/commands.md',
        content: <<<'MARKDOWN'
---
title: Telemetry Commands
commands:
  - name: Telemetry Status
    slug: telemetry-status
    syntax: /telemetry status
    description: Shows telemetry status.
    category: diagnostics
---

# Telemetry Commands
MARKDOWN,
        etag: '"telemetry-etag"',
    );
}

function telemetryNotModifiedMarkdownFile(): GitHubMarkdownFile
{
    return new GitHubMarkdownFile(
        path: 'docs/unchanged.md',
        content: null,
        etag: '"telemetry-unchanged-etag"',
        notModified: true,
    );
}

/**
 * @param  array<string, mixed>  $payload
 * @return array{json: string, signature: string}
 */
function signedTelemetryPayload(array $payload, string $secret): array
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    return [
        'json' => $json,
        'signature' => 'sha256='.hash_hmac('sha256', $json, $secret),
    ];
}

it('propagates a valid X-Request-Id from a webhook to the run, log entries, and response header', function (): void {
    telemetryPluginVersion();
    config(['commandsphere.github.webhook_secret' => 'telemetry-secret']);
    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            return [telemetryMarkdownFile(), telemetryNotModifiedMarkdownFile()];
        }
    });

    $payload = signedTelemetryPayload([
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'commandsphere/telemetry-plugin'],
    ], 'telemetry-secret');
    $requestId = 'webhook-correlation-12345';

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/github',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $payload['signature'],
            'HTTP_X_REQUEST_ID' => $requestId,
        ],
        content: $payload['json'],
    );

    $response->assertAccepted()
        ->assertHeader('X-Request-Id', $requestId);

    $run = IngestionRun::query()->firstOrFail();

    expect($run->correlation_id)->toBe($requestId)
        ->and($run->status)->toBe('success')
        ->and($run->log)->not->toBeEmpty()
        ->and($run->log[0]['code'])->toBe('document_not_modified')
        ->and(collect($run->log)->pluck('correlation_id')->unique()->all())->toBe([$requestId]);

    $enqueued = telemetryEventsNamed('ingestion.enqueued');
    $started = telemetryEventsNamed('ingestion.started');
    $completed = telemetryEventsNamed('ingestion.completed');
    $accepted = telemetryEventsNamed('webhook.github.accepted');

    expect($enqueued)->toHaveCount(1)
        ->and($enqueued[0]['context']['correlation_id'])->toBe($requestId)
        ->and($enqueued[0]['context']['ingestion_run_id'])->toBe($run->id)
        ->and($started)->toHaveCount(1)
        ->and($started[0]['context']['correlation_id'])->toBe($requestId)
        ->and($completed)->toHaveCount(1)
        ->and($completed[0]['context']['correlation_id'])->toBe($requestId)
        ->and($completed[0]['context']['status'])->toBe('success')
        ->and($accepted)->toHaveCount(1)
        ->and($accepted[0]['context']['correlation_id'])->toBe($requestId)
        ->and($accepted[0]['context']['queued'])->toBe(1);
});

it('discards an invalid X-Request-Id and substitutes a generated one everywhere downstream', function (): void {
    telemetryPluginVersion();
    config(['commandsphere.github.webhook_secret' => 'telemetry-secret']);
    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            return [telemetryMarkdownFile()];
        }
    });

    $payload = signedTelemetryPayload([
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'commandsphere/telemetry-plugin'],
    ], 'telemetry-secret');
    $unsafeRequestId = str_repeat('a', 300);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/github',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $payload['signature'],
            'HTTP_X_REQUEST_ID' => $unsafeRequestId,
        ],
        content: $payload['json'],
    );

    $response->assertAccepted();
    $issuedId = $response->headers->get('X-Request-Id');

    expect($issuedId)->not->toBeNull()
        ->and($issuedId)->not->toBe($unsafeRequestId)
        ->and(CorrelationId::isValid($issuedId))->toBeTrue();

    $run = IngestionRun::query()->firstOrFail();

    expect($run->correlation_id)->toBe($issuedId);
});

it('rejects a trailing-terminator X-Request-Id and substitutes a UUID in the header and the run column', function (string $terminator) {
    telemetryPluginVersion();
    config(['commandsphere.github.webhook_secret' => 'telemetry-secret']);
    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            return [telemetryMarkdownFile()];
        }
    });

    $payload = signedTelemetryPayload([
        'ref' => 'refs/heads/main',
        'repository' => ['full_name' => 'commandsphere/telemetry-plugin'],
    ], 'telemetry-secret');
    $injectedRequestId = 'injected-id'.$terminator;

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/github',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $payload['signature'],
            'HTTP_X_REQUEST_ID' => $injectedRequestId,
        ],
        content: $payload['json'],
    );

    $response->assertAccepted();
    $issuedId = $response->headers->get('X-Request-Id');

    expect($issuedId)->not->toBeNull()
        ->and($issuedId)->not->toBe($injectedRequestId)
        ->and(CorrelationId::isValid($issuedId))->toBeTrue()
        ->and(preg_match('/\A[0-9a-f-]{36}\z/i', $issuedId))->toBe(1);

    $run = IngestionRun::query()->firstOrFail();

    expect($run->correlation_id)->toBe($issuedId)
        ->and($run->correlation_id)->not->toContain("\n")
        ->and($run->correlation_id)->not->toContain("\r");
})->with([
    'trailing LF' => ["\n"],
    'trailing CR' => ["\r"],
    'trailing CRLF' => ["\r\n"],
]);

it('rejects an unsigned webhook and logs webhook.github.rejected with a correlation id', function (): void {
    telemetryPluginVersion();
    config(['commandsphere.github.webhook_secret' => 'telemetry-secret']);

    $this->call(
        'POST',
        '/api/v1/webhooks/github',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ],
        content: json_encode([
            'ref' => 'refs/heads/main',
            'repository' => ['full_name' => 'commandsphere/telemetry-plugin'],
        ], JSON_THROW_ON_ERROR),
    )->assertUnauthorized();

    $rejected = telemetryEventsNamed('webhook.github.rejected');

    expect($rejected)->toHaveCount(1)
        ->and($rejected[0]['context']['correlation_id'])->not->toBeEmpty()
        ->and($rejected[0]['context']['reason'])->toBe('invalid_signature');
});

it('records ingestion.failed with the ADR-27 taxonomy code and the run correlation id', function (): void {
    $version = telemetryPluginVersion();
    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            throw GitHubRateLimitException::forRepository($plugin->repository_url ?? 'fixture');
        }
    });

    $service = app(IngestionService::class);
    $run = $service->start($version, 'manual', correlationId: 'failure-correlation-id');
    $run = $service->run($version, $run);

    expect($run->status)->toBe('failed')
        ->and($run->correlation_id)->toBe('failure-correlation-id')
        ->and($run->log[0]['code'])->toBe('git_hub_rate_limit_exception')
        ->and($run->log[0]['correlation_id'])->toBe('failure-correlation-id');

    $failed = telemetryEventsNamed('ingestion.failed');

    expect($failed)->toHaveCount(1)
        ->and($failed[0]['context']['correlation_id'])->toBe('failure-correlation-id')
        ->and($failed[0]['context']['code'])->toBe('git_hub_rate_limit_exception');
});

it('keeps a reused run correlation id stable while the new request logs its own enqueue event', function (): void {
    $version = telemetryPluginVersion();
    $service = app(IngestionService::class);

    $firstRun = $service->start($version, 'manual', correlationId: 'first-caller-id');
    $secondRun = $service->start($version, 'manual', correlationId: 'second-caller-id');

    expect($secondRun->id)->toBe($firstRun->id)
        ->and($secondRun->correlation_id)->toBe('first-caller-id');

    $enqueued = telemetryEventsNamed('ingestion.enqueued');

    expect($enqueued)->toHaveCount(2)
        ->and($enqueued[0]['context']['correlation_id'])->toBe('first-caller-id')
        ->and($enqueued[0]['context']['reused'])->toBeFalse()
        ->and($enqueued[1]['context']['correlation_id'])->toBe('second-caller-id')
        ->and($enqueued[1]['context']['reused'])->toBeTrue()
        ->and($enqueued[1]['context']['ingestion_run_id'])->toBe($firstRun->id);
});

it('gives every scheduled plugin sync run its own correlation id', function (): void {
    telemetryPluginVersion();

    $secondCommunity = Community::factory()->create();
    $secondPlugin = Plugin::factory()->create([
        'community_id' => $secondCommunity->id,
        'name' => 'Telemetry Plugin Two',
        'slug' => 'telemetry-plugin-two',
        'repository_url' => 'commandsphere/telemetry-plugin-two',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);
    PluginVersion::factory()->latest()->create([
        'plugin_id' => $secondPlugin->id,
        'version' => '1.0.0',
        'git_ref' => 'main',
    ]);

    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            return [telemetryMarkdownFile()];
        }
    });

    Artisan::call('commandsphere:sync-scheduled');

    $runs = IngestionRun::query()->orderBy('id')->get();

    expect($runs)->toHaveCount(2)
        ->and($runs[0]->correlation_id)->not->toBeNull()
        ->and($runs[1]->correlation_id)->not->toBeNull()
        ->and($runs[0]->correlation_id)->not->toBe($runs[1]->correlation_id);
});
