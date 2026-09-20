<?php

namespace App\Services\GitHub;

use App\Contracts\GitHubClient;
use App\Data\GitHubMarkdownFile;
use App\Exceptions\GitHubAuthenticationException;
use App\Exceptions\GitHubClientException;
use App\Exceptions\GitHubMalformedResponseException;
use App\Exceptions\GitHubRateLimitException;
use App\Exceptions\GitHubRepositoryNotFoundException;
use App\Exceptions\GitHubTransientErrorException;
use App\Exceptions\GitHubTreeTruncatedException;
use App\Exceptions\GitHubValidationException;
use App\Models\Plugin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class HttpGitHubClient implements GitHubClient
{
    /**
     * @return list<GitHubMarkdownFile>
     */
    public function markdownFiles(Plugin $plugin, ?string $branch = null): array
    {
        $repository = $this->repositoryName($plugin);
        $branch ??= $plugin->default_branch;

        $tree = $this->request($repository, "git/trees/{$branch}", [
            'recursive' => '1',
        ]);

        $treeJson = $tree->json();

        if (! is_array($treeJson) || ! is_array($treeJson['tree'] ?? null)) {
            throw GitHubMalformedResponseException::forRepository($repository, 'response body did not include a tree');
        }

        if ($treeJson['truncated'] ?? false) {
            throw GitHubTreeTruncatedException::forRepository($repository);
        }

        $files = collect($treeJson['tree'])
            ->filter(fn (array $node): bool => ($node['type'] ?? null) === 'blob')
            ->pluck('path')
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.md'))
            ->filter(fn (string $path): bool => $this->isInsideDocumentationPath($path, $plugin->documentation_path))
            ->values();

        return $files
            ->map(fn (string $path): GitHubMarkdownFile => $this->readMarkdownFile($repository, $branch, $path))
            ->all();
    }

    /**
     * Perform a GitHub API request and fail closed on every response that
     * is not a trusted success.
     *
     * Every non-2xx/304 status, and every connection-level failure, is
     * mapped to a stable {@see GitHubClientException}
     * subtype so callers never mistake a GitHub-side failure for an empty
     * or successful result.
     *
     * @param  array<string, string>  $query
     */
    private function request(string $repository, string $uri, array $query = [], array $headers = []): Response
    {
        $request = Http::baseUrl("https://api.github.com/repos/{$repository}")
            ->acceptJson()
            ->withHeaders(array_filter([
                'Authorization' => config('commandsphere.github.token') ? 'Bearer '.config('commandsphere.github.token') : null,
                'X-GitHub-Api-Version' => '2022-11-28',
            ] + $headers));

        try {
            $response = $request->get($uri, $query);
        } catch (ConnectionException) {
            throw GitHubTransientErrorException::forRepository($repository, 'connection failure');
        }

        $status = $response->status();

        if ($status === 304) {
            return $response;
        }

        if ($status === 404) {
            throw GitHubRepositoryNotFoundException::forRepository($repository);
        }

        if ($status === 429 || ($status === 403 && $this->isRateLimitSignal($response))) {
            throw GitHubRateLimitException::forRepository($repository);
        }

        if ($status === 401 || $status === 403) {
            throw GitHubAuthenticationException::forRepository($repository, $status);
        }

        if ($status === 409 || $status === 422) {
            throw GitHubValidationException::forRepository($repository, $status);
        }

        if ($status >= 500) {
            throw GitHubTransientErrorException::forRepository($repository, "HTTP {$status}");
        }

        if (! $response->successful()) {
            throw GitHubMalformedResponseException::forRepository($repository, "unexpected HTTP {$status}");
        }

        return $response;
    }

    /**
     * GitHub signals primary rate limiting on a 403 response via the
     * `X-RateLimit-Remaining` header. A 403 without that signal is an
     * authentication/permission failure instead.
     */
    private function isRateLimitSignal(Response $response): bool
    {
        return $response->header('X-RateLimit-Remaining') === '0';
    }

    private function readMarkdownFile(string $repository, string $branch, string $path): GitHubMarkdownFile
    {
        $cacheKey = $this->etagCacheKey($repository, $branch, $path);
        $etag = Cache::get($cacheKey);

        $response = $this->request(
            $repository,
            'contents/'.$path,
            ['ref' => $branch],
            $etag ? ['If-None-Match' => $etag] : [],
        );

        if ($response->status() === 304) {
            return new GitHubMarkdownFile($path, null, $etag, true);
        }

        $nextEtag = $response->header('ETag');

        if ($nextEtag !== null) {
            Cache::put($cacheKey, $nextEtag);
        }

        $content = $response->json('content');
        $encoding = $response->json('encoding');

        if (! is_string($content) || $encoding !== 'base64') {
            throw GitHubMalformedResponseException::forRepository($repository, "unsupported content encoding for [{$path}]");
        }

        $decoded = base64_decode(str_replace("\n", '', $content), true);

        if ($decoded === false) {
            throw GitHubMalformedResponseException::forRepository($repository, "invalid base64 content for [{$path}]");
        }

        return new GitHubMarkdownFile(
            path: $path,
            content: $decoded,
            etag: $nextEtag,
        );
    }

    private function repositoryName(Plugin $plugin): string
    {
        $repository = $plugin->repository_url ?? '';

        if (preg_match('#github\.com[:/]([^/]+)/([^/.]+)#', $repository, $matches) === 1) {
            return $matches[1].'/'.$matches[2];
        }

        if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) === 1) {
            return $repository;
        }

        throw new RuntimeException("Plugin [{$plugin->id}] does not have a valid GitHub repository.");
    }

    private function isInsideDocumentationPath(string $path, string $documentationPath): bool
    {
        $documentationPath = trim($documentationPath, '/');

        return $documentationPath === '' || $path === $documentationPath || Str::startsWith($path, $documentationPath.'/');
    }

    private function etagCacheKey(string $repository, string $branch, string $path): string
    {
        return 'commandsphere:github:etag:'.sha1($repository.'|'.$branch.'|'.$path);
    }
}
