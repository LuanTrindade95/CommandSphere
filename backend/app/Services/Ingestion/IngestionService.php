<?php

namespace App\Services\Ingestion;

use App\Contracts\GitHubClient;
use App\Data\ParsedCommand;
use App\Data\ParsedDocument;
use App\Events\CommandIndexUpdated;
use App\Events\IngestionRunStatusChanged;
use App\Exceptions\GitHubClientException;
use App\Models\Category;
use App\Models\Command;
use App\Models\Document;
use App\Models\IngestionRun;
use App\Models\PluginVersion;
use App\Services\Discovery\DiscoveryCache;
use App\Services\Markdown\MarkdownParser;
use App\Support\CorrelationId;
use App\Support\Telemetry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IngestionService
{
    public function __construct(
        private readonly GitHubClient $github,
        private readonly MarkdownParser $parser,
        private readonly DiscoveryCache $cache,
    ) {}

    /**
     * Start (or reuse) an ingestion run.
     *
     * The correlation ID identifies the caller's request, not the run: if
     * an active run is reused, the run keeps the correlation ID of whoever
     * created it, and this call's own ID is only recorded on the
     * `ingestion.enqueued` telemetry event, referencing the reused run.
     * Run history is never rewritten.
     */
    public function start(PluginVersion $pluginVersion, string $source = 'manual', bool $force = false, ?string $correlationId = null): IngestionRun
    {
        $requestCorrelationId = $correlationId ?? CorrelationId::generate();

        $run = DB::transaction(function () use ($pluginVersion, $source, $force, $requestCorrelationId): IngestionRun {
            if (! $force) {
                $existingRun = IngestionRun::query()
                    ->where('plugin_version_id', $pluginVersion->id)
                    ->whereIn('status', ['queued', 'running'])
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($existingRun !== null) {
                    return $existingRun;
                }
            }

            return IngestionRun::query()->create([
                'plugin_version_id' => $pluginVersion->id,
                'source' => $source,
                'correlation_id' => $requestCorrelationId,
                'status' => 'queued',
                'stats' => [
                    'docs_parsed' => 0,
                    'commands_extracted' => 0,
                    'warnings' => 0,
                ],
                'log' => [],
            ]);
        });

        if ($run->wasRecentlyCreated) {
            IngestionRunStatusChanged::dispatch($run);
        }

        Telemetry::event('ingestion.enqueued', [
            'correlation_id' => $requestCorrelationId,
            'ingestion_run_id' => $run->id,
            'plugin_version_id' => $pluginVersion->id,
            'plugin_id' => $pluginVersion->plugin_id,
            'community_id' => $pluginVersion->plugin?->community_id,
            'source' => $source,
            'reused' => ! $run->wasRecentlyCreated,
            'user_id' => Auth::id(),
        ]);

        return $run;
    }

    public function run(PluginVersion $pluginVersion, ?IngestionRun $run = null): IngestionRun
    {
        $pluginVersion->loadMissing('plugin');
        $run ??= $this->start($pluginVersion);
        $correlationId = $run->correlation_id ?? CorrelationId::generate();
        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
        ]);
        IngestionRunStatusChanged::dispatch($run->refresh());

        Telemetry::event('ingestion.started', [
            'correlation_id' => $correlationId,
            'ingestion_run_id' => $run->id,
            'plugin_version_id' => $pluginVersion->id,
            'plugin_id' => $pluginVersion->plugin_id,
            'community_id' => $pluginVersion->plugin?->community_id,
            'source' => $run->source,
        ]);

        $stats = [
            'docs_parsed' => 0,
            'commands_extracted' => 0,
            'warnings' => 0,
        ];
        $log = [];

        try {
            $files = $this->github->markdownFiles($pluginVersion->plugin, $pluginVersion->git_ref ?: $pluginVersion->plugin->default_branch);
        } catch (GitHubClientException $exception) {
            return $this->fail($run, $stats, [[
                'level' => 'error',
                'code' => $exception->failureCode(),
                'message' => $exception->getMessage(),
                'correlation_id' => $correlationId,
            ]], $correlationId);
        }

        foreach ($files as $file) {
            if ($file->notModified) {
                $log[] = [
                    'level' => 'info',
                    'code' => 'document_not_modified',
                    'path' => $file->path,
                    'message' => "Skipped unchanged markdown file [{$file->path}].",
                    'correlation_id' => $correlationId,
                ];

                continue;
            }

            try {
                $parsedDocument = $this->parser->parse($file);
                $result = DB::transaction(fn (): array => $this->persistParsedDocument($pluginVersion, $parsedDocument));

                $stats['docs_parsed']++;
                $stats['commands_extracted'] += $result['commands_extracted'];
                $stats['warnings'] += count($parsedDocument->warnings);

                foreach ($parsedDocument->warnings as $warning) {
                    $log[] = [
                        'level' => 'warning',
                        'code' => 'markdown_warning',
                        'path' => $parsedDocument->path,
                        'message' => $warning,
                        'correlation_id' => $correlationId,
                    ];
                }
            } catch (Throwable $exception) {
                $stats['warnings']++;
                $log[] = [
                    'level' => 'warning',
                    'code' => 'document_parse_failed',
                    'path' => $file->path,
                    'message' => $exception->getMessage(),
                    'correlation_id' => $correlationId,
                ];
            }
        }

        $status = $stats['warnings'] > 0 ? 'partial' : 'success';

        $run->update([
            'status' => $status,
            'stats' => $stats,
            'log' => $log,
            'finished_at' => now(),
        ]);

        Telemetry::event('ingestion.completed', [
            'correlation_id' => $correlationId,
            'ingestion_run_id' => $run->id,
            'plugin_version_id' => $pluginVersion->id,
            'plugin_id' => $pluginVersion->plugin_id,
            'community_id' => $pluginVersion->plugin?->community_id,
            'status' => $status,
            'docs_parsed' => $stats['docs_parsed'],
            'commands_extracted' => $stats['commands_extracted'],
            'warnings' => $stats['warnings'],
        ]);

        if ($stats['docs_parsed'] > 0) {
            $pluginVersion->commands()->with(['category', 'pluginVersion.plugin.community'])->get()->searchable();
            $this->cache->invalidate();
            $community = $pluginVersion->plugin?->community;

            if ($community !== null) {
                CommandIndexUpdated::dispatch($community);
            }
        }

        $run = $run->refresh();
        IngestionRunStatusChanged::dispatch($run);

        return $run;
    }

    /**
     * @return array{commands_extracted: int}
     */
    private function persistParsedDocument(PluginVersion $pluginVersion, ParsedDocument $parsedDocument): array
    {
        $document = Document::query()->updateOrCreate(
            [
                'plugin_version_id' => $pluginVersion->id,
                'path' => $parsedDocument->path,
            ],
            [
                'title' => $parsedDocument->title,
                'frontmatter' => $parsedDocument->frontmatter,
                'content_raw' => $parsedDocument->contentRaw,
                'content_html' => $parsedDocument->contentHtml,
            ],
        );

        $seenSlugs = [];

        foreach ($parsedDocument->commands as $parsedCommand) {
            $seenSlugs[] = $parsedCommand->slug;
            $categoryId = $this->categoryId($parsedCommand);

            Command::query()->updateOrCreate(
                [
                    'plugin_version_id' => $pluginVersion->id,
                    'slug' => $parsedCommand->slug,
                ],
                [
                    'document_id' => $document->id,
                    'category_id' => $categoryId,
                    'name' => $parsedCommand->name,
                    'syntax' => $parsedCommand->syntax,
                    'description' => $parsedCommand->description,
                    'aliases' => $parsedCommand->aliases,
                    'parameters' => $parsedCommand->parameters,
                ],
            );
        }

        $staleCommands = Command::query()
            ->where('plugin_version_id', $pluginVersion->id)
            ->where('document_id', $document->id)
            ->when($seenSlugs !== [], fn ($query) => $query->whereNotIn('slug', $seenSlugs))
            ->get();

        $staleCommands->unsearchable();
        $staleCommands->each->delete();

        return [
            'commands_extracted' => count($seenSlugs),
        ];
    }

    private function categoryId(ParsedCommand $command): ?int
    {
        if ($command->category === null || trim($command->category) === '') {
            return null;
        }

        return Category::query()->firstOrCreate(
            ['slug' => Str::slug($command->category)],
            ['name' => Str::headline($command->category)],
        )->id;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  list<array<string, mixed>>  $log
     */
    private function fail(IngestionRun $run, array $stats, array $log, ?string $correlationId = null): IngestionRun
    {
        $run->update([
            'status' => 'failed',
            'stats' => $stats,
            'log' => $log,
            'finished_at' => now(),
        ]);

        $run = $run->refresh();
        IngestionRunStatusChanged::dispatch($run);

        // The failure code is consumed from the `code` key already
        // persisted in the log entry, per ADR-27/BRAIN-007: never
        // recomputed, never renamed.
        $run->loadMissing('pluginVersion.plugin');
        $pluginVersion = $run->pluginVersion;

        Telemetry::event('ingestion.failed', [
            'correlation_id' => $correlationId ?? $run->correlation_id,
            'ingestion_run_id' => $run->id,
            'plugin_version_id' => $run->plugin_version_id,
            'plugin_id' => $pluginVersion?->plugin_id,
            'community_id' => $pluginVersion?->plugin?->community_id,
            'code' => $log[0]['code'] ?? null,
        ]);

        return $run;
    }
}
