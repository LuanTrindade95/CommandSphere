<?php

namespace App\Data;

class ParsedDocument
{
    /**
     * @param  array<string, mixed>  $frontmatter
     * @param  list<ParsedCommand>  $commands
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $path,
        public readonly string $title,
        public readonly array $frontmatter,
        public readonly string $contentRaw,
        public readonly string $contentHtml,
        public readonly array $commands,
        public readonly array $warnings,
    ) {}
}
