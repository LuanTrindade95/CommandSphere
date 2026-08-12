<?php

namespace App\Data;

use App\Models\Command;
use Spatie\LaravelData\Data;

class CommandData extends Data
{
    /**
     * @param  list<string>  $aliases
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public int $id,
        public int $pluginVersionId,
        public ?int $documentId,
        public ?int $categoryId,
        public string $name,
        public string $slug,
        public ?string $syntax,
        public ?string $description,
        public array $aliases,
        public array $parameters,
    ) {}

    public static function fromModel(Command $command): self
    {
        return new self(
            id: $command->id,
            pluginVersionId: $command->plugin_version_id,
            documentId: $command->document_id,
            categoryId: $command->category_id,
            name: $command->name,
            slug: $command->slug,
            syntax: $command->syntax,
            description: $command->description,
            aliases: $command->aliases ?? [],
            parameters: $command->parameters ?? [],
        );
    }
}
