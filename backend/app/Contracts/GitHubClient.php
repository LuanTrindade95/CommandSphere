<?php

namespace App\Contracts;

use App\Data\GitHubMarkdownFile;
use App\Models\Plugin;

interface GitHubClient
{
    /**
     * @return list<GitHubMarkdownFile>
     */
    public function markdownFiles(Plugin $plugin, ?string $branch = null): array;
}
