<?php

use App\Contracts\GitHubClient;
use App\Events\CommandIndexUpdated;
use App\Events\IngestionRunStatusChanged;
use App\Exceptions\GitHubAuthenticationException;
use App\Exceptions\GitHubClientException;
use App\Exceptions\GitHubMalformedResponseException;
use App\Exceptions\GitHubRateLimitException;
use App\Exceptions\GitHubRepositoryNotFoundException;
use App\Exceptions\GitHubTransientErrorException;
use App\Exceptions\GitHubTreeTruncatedException;
use App\Exceptions\GitHubValidationException;
use App\Models\Community;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Services\GitHub\HttpGitHubClient;
use App\Services\Ingestion\IngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Every non-success response the GitHub API can return, mapped to the
 * stable failure code and exception type HttpGitHubClient must raise.
 * This is the "after" column of the F-006 fix; the "before" column
 * (documented once via a throwaway repro scaffold) showed every one of
 * these except 403-with-rate-limit-signal and 404 producing either a
 * silent empty tree or an uncaught RuntimeException.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function githubFailureScenarios(): array
{
    return [
        '401 unauthenticated' => [GitHubAuthenticationException::class, 'github_authentication_failed'],
        '403 without rate-limit signal (permission)' => [GitHubAuthenticationException::class, 'github_authentication_failed'],
        '403 with rate-limit signal' => [GitHubRateLimitException::class, 'git_hub_rate_limit_exception'],
        '429 secondary rate limit' => [GitHubRateLimitException::class, 'git_hub_rate_limit_exception'],
        '409 conflict' => [GitHubValidationException::class, 'github_validation_failed'],
        '422 unprocessable' => [GitHubValidationException::class, 'github_validation_failed'],
        '500 server error' => [GitHubTransientErrorException::class, 'github_transient_error'],
        '503 unavailable' => [GitHubTransientErrorException::class, 'github_transient_error'],
    ];
}

function githubReproPlugin(): Plugin
{
    return new Plugin([
        'repository_url' => 'commandsphere/repro',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);
}

it('fails closed with a stable, distinguishable code for 401', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

    expect(fn () => (new HttpGitHubClient)->markdownFiles(githubReproPlugin()))
        ->toThrow(GitHubAuthenticationException::class);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
    } catch (GitHubClientException $exception) {
        expect($exception->failureCode())->toBe('github_authentication_failed')
            ->and($exception->getMessage())->not->toContain('Bearer')
            ->and($exception->getMessage())->not->toContain('Authorization');
    }
});

it('treats 403 without a rate-limit signal as an authentication/permission failure', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Resource not accessible by integration'], 403)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubAuthenticationException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubAuthenticationException::class)
            ->and($exception->failureCode())->toBe('github_authentication_failed');
    }
});

it('treats 403 with a rate-limit signal as rate limiting', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Forbidden'], 403, ['X-RateLimit-Remaining' => '0'])]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubRateLimitException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubRateLimitException::class)
            ->and($exception->failureCode())->toBe('git_hub_rate_limit_exception');
    }
});

it('treats 429 as rate limiting', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Too many requests'], 429)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubRateLimitException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubRateLimitException::class)
            ->and($exception->failureCode())->toBe('git_hub_rate_limit_exception');
    }
});

it('keeps repository/ref-not-found on 404 unchanged', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubRepositoryNotFoundException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubRepositoryNotFoundException::class)
            ->and($exception->failureCode())->toBe('git_hub_repository_not_found_exception');
    }
});

it('fails closed on 409 and 422 as validation failures', function (int $status): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'invalid'], $status)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubValidationException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubValidationException::class)
            ->and($exception->failureCode())->toBe('github_validation_failed');
    }
})->with([409, 422]);

it('fails closed on 5xx responses as transient errors', function (int $status): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'server error'], $status)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubTransientErrorException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubTransientErrorException::class)
            ->and($exception->failureCode())->toBe('github_transient_error');
    }
})->with([500, 503]);

it('fails closed on a connection failure as a transient error', function (): void {
    Http::fake(['api.github.com/*' => fn () => throw new ConnectionException('Connection timed out')]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubTransientErrorException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubTransientErrorException::class)
            ->and($exception->failureCode())->toBe('github_transient_error');
    }
});

it('fails closed on a 200 response with a non-JSON body', function (): void {
    Http::fake(['api.github.com/*' => Http::response('not json at all', 200, ['Content-Type' => 'text/plain'])]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubMalformedResponseException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubMalformedResponseException::class)
            ->and($exception->failureCode())->toBe('github_malformed_response');
    }
});

it('fails closed on a 200 response missing the tree key', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['sha' => 'abc'], 200)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubMalformedResponseException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubMalformedResponseException::class)
            ->and($exception->failureCode())->toBe('github_malformed_response');
    }
});

it('fails closed with a dedicated code when the tree is truncated', function (): void {
    Http::fake(['api.github.com/*' => Http::response([
        'tree' => [['path' => 'docs/a.md', 'type' => 'blob']],
        'truncated' => true,
    ], 200)]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubTreeTruncatedException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubTreeTruncatedException::class)
            ->and($exception->failureCode())->toBe('github_tree_truncated');
    }
});

it('fails closed on invalid base64 file content', function (): void {
    Http::fake([
        'api.github.com/repos/*/git/trees/*' => Http::response([
            'tree' => [['path' => 'docs/a.md', 'type' => 'blob']],
            'truncated' => false,
        ], 200),
        'api.github.com/repos/*/contents/*' => Http::response([
            'content' => '###not-valid-base64###',
            'encoding' => 'base64',
        ], 200),
    ]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubMalformedResponseException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubMalformedResponseException::class)
            ->and($exception->failureCode())->toBe('github_malformed_response');
    }
});

it('fails closed on an unsupported content encoding', function (): void {
    Http::fake([
        'api.github.com/repos/*/git/trees/*' => Http::response([
            'tree' => [['path' => 'docs/a.md', 'type' => 'blob']],
            'truncated' => false,
        ], 200),
        'api.github.com/repos/*/contents/*' => Http::response([
            'content' => 'aGVsbG8=',
            'encoding' => 'utf-8',
        ], 200),
    ]);

    try {
        (new HttpGitHubClient)->markdownFiles(githubReproPlugin());
        expect(false)->toBeTrue('Expected GitHubMalformedResponseException to be thrown.');
    } catch (GitHubClientException $exception) {
        expect($exception)->toBeInstanceOf(GitHubMalformedResponseException::class)
            ->and($exception->failureCode())->toBe('github_malformed_response');
    }
});

it('still honors a 304 response as unchanged content and keeps the cached ETag', function (): void {
    Http::fake([
        'api.github.com/repos/*/git/trees/*' => Http::sequence()
            ->push(['tree' => [['path' => 'docs/a.md', 'type' => 'blob']], 'truncated' => false], 200)
            ->push(['tree' => [['path' => 'docs/a.md', 'type' => 'blob']], 'truncated' => false], 200),
        'api.github.com/repos/*/contents/*' => Http::sequence()
            ->push(['content' => base64_encode('hello'), 'encoding' => 'base64'], 200, ['ETag' => '"v1"'])
            ->push(null, 304),
    ]);

    $client = new HttpGitHubClient;
    $plugin = githubReproPlugin();

    $first = $client->markdownFiles($plugin);
    expect($first)->toHaveCount(1)
        ->and($first[0]->content)->toBe('hello')
        ->and($first[0]->notModified)->toBeFalse();

    $second = $client->markdownFiles($plugin);
    expect($second)->toHaveCount(1)
        ->and($second[0]->notModified)->toBeTrue()
        ->and($second[0]->content)->toBeNull();
});

it('ends the ingestion run as failed with the transient-error code instead of leaving it stuck running', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'server error'], 503)]);

    $community = Community::factory()->create();
    $plugin = Plugin::factory()->create([
        'community_id' => $community->id,
        'repository_url' => 'commandsphere/repro',
        'documentation_path' => 'docs',
        'default_branch' => 'main',
    ]);
    $pluginVersion = PluginVersion::factory()->latest()->create([
        'plugin_id' => $plugin->id,
        'version' => '1.0.0',
        'git_ref' => 'main',
    ]);

    app()->bind(GitHubClient::class, fn (): HttpGitHubClient => new HttpGitHubClient);

    Event::fake([CommandIndexUpdated::class, IngestionRunStatusChanged::class]);
    config(['queue.default' => 'sync', 'scout.driver' => 'null']);

    $run = app(IngestionService::class)->run($pluginVersion);

    expect($run->status)->toBe('failed')
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->log[0]['code'])->toBe('github_transient_error')
        ->and(collect($run->log)->pluck('message')->implode(' '))
        ->not->toContain('Bearer');
});
