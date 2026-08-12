<?php

namespace App\Services\Markdown;

use App\Data\GitHubMarkdownFile;
use App\Data\ParsedCommand;
use App\Data\ParsedDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class MarkdownParser
{
    public function __construct(
        private readonly GithubFlavoredMarkdownConverter $converter = new GithubFlavoredMarkdownConverter,
    ) {}

    public function parse(GitHubMarkdownFile $file): ParsedDocument
    {
        $raw = $file->content ?? '';
        [$frontmatter, $body, $warnings] = $this->extractFrontmatter($raw, $file->path);
        $title = $this->title($frontmatter, $body, $file->path);
        $frontmatterCommands = $this->commandsFromFrontmatter($frontmatter, $file->path, $warnings);
        $headingCommands = $this->commandsFromHeadings($body, $file->path, $warnings);
        $commands = $this->uniqueCommands([...$frontmatterCommands, ...$headingCommands], $warnings, $file->path);

        if ($commands === []) {
            $warnings[] = "Document [{$file->path}] did not declare commands using the supported convention.";
        }

        return new ParsedDocument(
            path: $file->path,
            title: $title,
            frontmatter: $frontmatter,
            contentRaw: $raw,
            contentHtml: (string) $this->converter->convert($body),
            commands: $commands,
            warnings: $warnings,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: list<string>}
     */
    private function extractFrontmatter(string $raw, string $path): array
    {
        if (! str_starts_with($raw, "---\n") && ! str_starts_with($raw, "---\r\n")) {
            return [[], $raw, []];
        }

        $normalized = str_replace("\r\n", "\n", $raw);
        $end = strpos($normalized, "\n---\n", 4);

        if ($end === false) {
            return [[], $raw, ["Document [{$path}] has malformed frontmatter delimiters."]];
        }

        $yaml = substr($normalized, 4, $end - 4);
        $body = substr($normalized, $end + 5);

        try {
            $frontmatter = Yaml::parse($yaml) ?? [];
        } catch (ParseException $exception) {
            return [[], $body, ["Document [{$path}] has invalid frontmatter: {$exception->getMessage()}"]];
        }

        if (! is_array($frontmatter)) {
            return [[], $body, ["Document [{$path}] frontmatter must be a map."]];
        }

        return [$frontmatter, $body, []];
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    private function title(array $frontmatter, string $body, string $path): string
    {
        $frontmatterTitle = Arr::get($frontmatter, 'title');

        if (is_string($frontmatterTitle) && $frontmatterTitle !== '') {
            return $frontmatterTitle;
        }

        if (preg_match('/^#\s+(.+)$/m', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return Str::headline(pathinfo($path, PATHINFO_FILENAME));
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     * @param  list<string>  $warnings
     * @return list<ParsedCommand>
     */
    private function commandsFromFrontmatter(array $frontmatter, string $path, array &$warnings): array
    {
        $commands = Arr::get($frontmatter, 'commands', []);

        if ($commands === null || $commands === []) {
            return [];
        }

        if (! is_array($commands)) {
            $warnings[] = "Document [{$path}] frontmatter commands must be a list.";

            return [];
        }

        return collect($commands)
            ->values()
            ->map(function (mixed $command, int $index) use ($path, &$warnings): ?ParsedCommand {
                if (! is_array($command)) {
                    $warnings[] = "Document [{$path}] frontmatter command #{$index} must be a map.";

                    return null;
                }

                return $this->commandFromMap($command, $path, "frontmatter command #{$index}", $warnings);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $warnings
     * @return list<ParsedCommand>
     */
    private function commandsFromHeadings(string $body, string $path, array &$warnings): array
    {
        if (preg_match_all('/^##\s+\/([A-Za-z0-9:_-]+)\s*$/m', $body, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $commands = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $commandName = $matches[1][$index][0];
            $sectionStart = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $sectionEnd = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($body);
            $section = trim(substr($body, $sectionStart, $sectionEnd - $sectionStart));
            $metadata = $this->metadataFromSection($section, $path, $commandName, $warnings);
            $metadata['name'] ??= Str::headline($commandName);
            $metadata['slug'] ??= Str::slug($commandName);

            $command = $this->commandFromMap($metadata, $path, "heading /{$commandName}", $warnings);

            if ($command !== null) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function metadataFromSection(string $section, string $path, string $commandName, array &$warnings): array
    {
        if (preg_match('/```ya?ml\s*(.*?)```/is', $section, $matches) === 1) {
            try {
                $metadata = Yaml::parse(trim($matches[1])) ?? [];
            } catch (ParseException $exception) {
                $warnings[] = "Document [{$path}] command [/{$commandName}] has invalid metadata: {$exception->getMessage()}";

                return [];
            }

            return is_array($metadata) ? $metadata : [];
        }

        $metadata = [];
        $descriptionLines = [];

        foreach (preg_split('/\R/', $section) ?: [] as $line) {
            if (preg_match('/^([A-Za-z_]+):\s*(.+)$/', trim($line), $matches) === 1) {
                $metadata[Str::snake($matches[1])] = $this->parseInlineValue($matches[2]);
            } elseif (trim($line) !== '' && ! str_starts_with(trim($line), '#')) {
                $descriptionLines[] = trim($line);
            }
        }

        if (! isset($metadata['description']) && $descriptionLines !== []) {
            $metadata['description'] = implode(' ', $descriptionLines);
        }

        return $metadata;
    }

    private function parseInlineValue(string $value): mixed
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return collect(explode(',', $value))
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->values()
                ->all();
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $warnings
     */
    private function commandFromMap(array $metadata, string $path, string $source, array &$warnings): ?ParsedCommand
    {
        $name = $metadata['name'] ?? null;
        $slug = $metadata['slug'] ?? null;
        $syntax = $metadata['syntax'] ?? null;

        if (! is_string($syntax) || trim($syntax) === '') {
            $warnings[] = "Document [{$path}] {$source} is missing required syntax.";

            return null;
        }

        if (! is_string($name) || trim($name) === '') {
            $name = Str::headline((string) ($slug ?: Str::after($syntax, '/')));
        }

        if (! is_string($slug) || trim($slug) === '') {
            $slug = Str::slug($name);
        }

        return new ParsedCommand(
            name: $name,
            slug: Str::slug($slug),
            syntax: $syntax,
            description: is_string($metadata['description'] ?? null) ? $metadata['description'] : null,
            aliases: $this->stringList($metadata['aliases'] ?? []),
            parameters: $this->parameters($metadata['params'] ?? $metadata['parameters'] ?? []),
            category: is_string($metadata['category'] ?? null) ? $metadata['category'] : null,
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  list<ParsedCommand>  $commands
     * @param  list<string>  $warnings
     * @return list<ParsedCommand>
     */
    private function uniqueCommands(array $commands, array &$warnings, string $path): array
    {
        $unique = [];

        foreach ($commands as $command) {
            if (array_key_exists($command->slug, $unique)) {
                $warnings[] = "Document [{$path}] declared duplicate command slug [{$command->slug}].";

                continue;
            }

            $unique[$command->slug] = $command;
        }

        return array_values($unique);
    }
}
