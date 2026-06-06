<?php

namespace App\Data;

use App\Models\Plugin;
use Spatie\LaravelData\Data;

class PluginData extends Data
{
    public function __construct(
        public int $id,
        public int $communityId,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $repositoryUrl,
        public string $documentationPath,
        public string $defaultBranch,
    ) {}

    public static function fromModel(Plugin $plugin): self
    {
        return new self(
            id: $plugin->id,
            communityId: $plugin->community_id,
            name: $plugin->name,
            slug: $plugin->slug,
            description: $plugin->description,
            repositoryUrl: $plugin->repository_url,
            documentationPath: $plugin->documentation_path,
            defaultBranch: $plugin->default_branch,
        );
    }
}
