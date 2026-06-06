<?php

namespace App\Services\Ingestion;

use App\Contracts\GitHubClient;
use App\Data\ParsedCommand;
use App\Data\ParsedDocument;
use App\Exceptions\GitHubRateLimitException;
use App\Exceptions\GitHubRepositoryNotFoundException;
use App\Models\Category;
use App\Models\Command;
use App\Models\Document;
use App\Models\IngestionRun;
use App\Models\PluginVersion;
use App\Services\Markdown\MarkdownParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IngestionService
{
    public function __construct(
        private readonly GitHubClient $github,
        private readonly MarkdownParser $parser,
    ) {}

    public function start(PluginVersion $pluginVersion): IngestionRun
    {
        return IngestionRun::query()->create([
            'plugin_version_id' => $pluginVersion->id,
            'status' => 'queued',
            'stats' => [
                'docs_parsed' => 0,
                'commands_extracted' => 0,
                'warnings' => 0,
            ],
            'log' => [],
        ]);
    }

    public function run(PluginVersion $pluginVersion, ?IngestionRun $run = null): IngestionRun
    {
        $pluginVersion->loadMissing('plugin');
        $run ??= $this->start($pluginVersion);
        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $stats = [
            'docs_parsed' => 0,
            'commands_extracted' => 0,
            'warnings' => 0,
        ];
        $log = [];

        try {
            $files = $this->github->markdownFiles($pluginVersion->plugin, $pluginVersion->git_ref ?: $pluginVersion->plugin->default_branch);
        } catch (GitHubRateLimitException|GitHubRepositoryNotFoundException $exception) {
            return $this->fail($run, $stats, [[
                'level' => 'error',
                'code' => Str::snake(class_basename($exception)),
                'message' => $exception->getMessage(),
            ]]);
        }

        foreach ($files as $file) {
            if ($file->notModified) {
                $log[] = [
                    'level' => 'info',
                    'code' => 'document_not_modified',
                    'path' => $file->path,
                    'message' => "Skipped unchanged markdown file [{$file->path}].",
                ];

                continue;
            }

            try {
                $parsedDocument = $this->parser->parse($file);
                $result = DB::transaction(fn (): array => Command::withoutSyncingToSearch(
                    fn (): array => $this->persistParsedDocument($pluginVersion, $parsedDocument),
                ));

                $stats['docs_parsed']++;
                $stats['commands_extracted'] += $result['commands_extracted'];
                $stats['warnings'] += count($parsedDocument->warnings);

                foreach ($parsedDocument->warnings as $warning) {
                    $log[] = [
                        'level' => 'warning',
                        'code' => 'markdown_warning',
                        'path' => $parsedDocument->path,
                        'message' => $warning,
                    ];
                }
            } catch (Throwable $exception) {
                $stats['warnings']++;
                $log[] = [
                    'level' => 'warning',
                    'code' => 'document_parse_failed',
                    'path' => $file->path,
                    'message' => $exception->getMessage(),
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

        return $run->refresh();
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

        Command::query()
            ->where('plugin_version_id', $pluginVersion->id)
            ->where('document_id', $document->id)
            ->when($seenSlugs !== [], fn ($query) => $query->whereNotIn('slug', $seenSlugs))
            ->delete();

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
    private function fail(IngestionRun $run, array $stats, array $log): IngestionRun
    {
        $run->update([
            'status' => 'failed',
            'stats' => $stats,
            'log' => $log,
            'finished_at' => now(),
        ]);

        return $run->refresh();
    }
}
