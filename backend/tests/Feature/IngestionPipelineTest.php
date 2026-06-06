<?php

use App\Contracts\GitHubClient;
use App\Data\GitHubMarkdownFile;
use App\Events\CommandIndexUpdated;
use App\Events\IngestionRunStatusChanged;
use App\Exceptions\GitHubRateLimitException;
use App\Models\Command;
use App\Models\Community;
use App\Models\Document;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Services\GitHub\FixtureGitHubClient;
use App\Services\Ingestion\IngestionService;
use App\Services\Markdown\MarkdownParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([
        CommandIndexUpdated::class,
        IngestionRunStatusChanged::class,
    ]);
});

function ingestionFixturePath(string $fixture): string
{
    return base_path('tests/fixtures/ingestion/'.$fixture);
}

function createIngestionPluginVersion(): PluginVersion
{
    $community = Community::factory()->create();
    $plugin = Plugin::factory()->create([
        'community_id' => $community->id,
        'repository_url' => 'commandsphere/fixtures',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);

    return PluginVersion::factory()->latest()->create([
        'plugin_id' => $plugin->id,
        'version' => '1.0.0',
        'git_ref' => 'main',
    ]);
}

function bindFixtureGitHubClient(string $fixture): void
{
    app()->bind(GitHubClient::class, fn (): FixtureGitHubClient => new FixtureGitHubClient(ingestionFixturePath($fixture)));
}

it('extracts commands from markdown frontmatter and heading fixtures', function (): void {
    $parser = app(MarkdownParser::class);
    $parsed = $parser->parse(new GitHubMarkdownFile(
        path: 'docs/commands.md',
        content: file_get_contents(ingestionFixturePath('current').'/docs/commands.md') ?: '',
    ));

    expect($parsed->title)->toBe('Moderation Commands')
        ->and($parsed->commands)->toHaveCount(3)
        ->and(collect($parsed->commands)->pluck('slug')->all())
        ->toBe(['ban-player', 'kick-player', 'mute-player']);
});

it('keeps malformed documents as warnings without failing the run', function (): void {
    bindFixtureGitHubClient('current');
    $pluginVersion = createIngestionPluginVersion();

    $run = app(IngestionService::class)->run($pluginVersion);

    expect($run->status)->toBe('partial')
        ->and($run->stats)->toMatchArray([
            'docs_parsed' => 4,
            'commands_extracted' => 4,
        ])
        ->and($run->stats['warnings'])->toBeGreaterThanOrEqual(2)
        ->and(Document::query()->count())->toBe(4)
        ->and(Command::query()->count())->toBe(4)
        ->and(collect($run->log)->pluck('message')->implode(' '))
        ->toContain('missing required syntax')
        ->toContain('did not declare commands');
});

it('does not duplicate documents or commands when ingesting the same content twice', function (): void {
    bindFixtureGitHubClient('current');
    $pluginVersion = createIngestionPluginVersion();
    $service = app(IngestionService::class);

    $firstRun = $service->run($pluginVersion);
    $secondRun = $service->run($pluginVersion);

    expect($firstRun->stats['commands_extracted'])->toBe(4)
        ->and($secondRun->stats['commands_extracted'])->toBe(4)
        ->and(Document::query()->count())->toBe(4)
        ->and(Command::query()->count())->toBe(4)
        ->and(Command::query()->where('slug', 'ban-player')->count())->toBe(1);
});

it('removes commands that disappeared from a document on re-sync', function (): void {
    bindFixtureGitHubClient('current');
    $pluginVersion = createIngestionPluginVersion();
    app(IngestionService::class)->run($pluginVersion);

    expect(Command::query()->where('slug', 'mute-player')->exists())->toBeTrue();

    bindFixtureGitHubClient('removed');
    $run = app(IngestionService::class)->run($pluginVersion);

    expect($run->stats['commands_extracted'])->toBe(3)
        ->and(Command::query()->count())->toBe(3)
        ->and(Command::query()->where('slug', 'mute-player')->exists())->toBeFalse();
});

it('marks rate limited GitHub reads as failed without corrupting existing data', function (): void {
    bindFixtureGitHubClient('current');
    $pluginVersion = createIngestionPluginVersion();
    app(IngestionService::class)->run($pluginVersion);

    $documentCount = Document::query()->count();
    $commandCount = Command::query()->count();

    app()->bind(GitHubClient::class, fn () => new class implements GitHubClient
    {
        public function markdownFiles(Plugin $plugin, ?string $branch = null): array
        {
            throw GitHubRateLimitException::forRepository($plugin->repository_url ?? 'fixture');
        }
    });

    $run = app(IngestionService::class)->run($pluginVersion);

    expect($run->status)->toBe('failed')
        ->and($run->log[0]['code'])->toBe('git_hub_rate_limit_exception')
        ->and(Document::query()->count())->toBe($documentCount)
        ->and(Command::query()->count())->toBe($commandCount);
});
