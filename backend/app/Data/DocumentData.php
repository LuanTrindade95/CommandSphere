<?php

namespace App\Data;

use App\Models\Document;
use Spatie\LaravelData\Data;

class DocumentData extends Data
{
    /**
     * @param  array<string, mixed>  $frontmatter
     */
    public function __construct(
        public int $id,
        public int $pluginVersionId,
        public string $path,
        public string $title,
        public array $frontmatter,
        public string $contentRaw,
        public string $contentHtml,
        public int $sortOrder,
    ) {}

    public static function fromModel(Document $document): self
    {
        return new self(
            id: $document->id,
            pluginVersionId: $document->plugin_version_id,
            path: $document->path,
            title: $document->title,
            frontmatter: $document->frontmatter ?? [],
            contentRaw: $document->content_raw,
            contentHtml: $document->content_html,
            sortOrder: $document->sort_order,
        );
    }
}
