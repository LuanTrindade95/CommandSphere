<?php

namespace App\Services\GitHub;

use App\Contracts\GitHubClient;
use App\Data\GitHubMarkdownFile;
use App\Exceptions\GitHubRateLimitException;
use App\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class FixtureGitHubClient implements GitHubClient
{
    public function __construct(
        private readonly string $fixturePath,
        private readonly bool $rateLimited = false,
    ) {}

    /**
     * @return list<GitHubMarkdownFile>
     */
    public function markdownFiles(Plugin $plugin, ?string $branch = null): array
    {
        if ($this->rateLimited) {
            throw GitHubRateLimitException::forRepository($plugin->repository_url ?? 'fixture');
        }

        if (! is_dir($this->fixturePath)) {
            throw new RuntimeException("Fixture path [{$this->fixturePath}] was not found.");
        }

        return collect(File::allFiles($this->fixturePath))
            ->filter(fn (\SplFileInfo $file): bool => Str::endsWith(Str::lower($file->getFilename()), '.md'))
            ->map(fn (\SplFileInfo $file): GitHubMarkdownFile => new GitHubMarkdownFile(
                path: str_replace('\\', '/', $file->getRelativePathname()),
                content: File::get($file->getPathname()),
                etag: '"fixture-'.sha1_file($file->getPathname()).'"',
            ))
            ->sortBy('path')
            ->values()
            ->all();
    }
}
