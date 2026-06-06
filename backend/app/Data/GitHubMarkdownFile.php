<?php

namespace App\Data;

class GitHubMarkdownFile
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $content,
        public readonly ?string $etag = null,
        public readonly bool $notModified = false,
    ) {}
}
