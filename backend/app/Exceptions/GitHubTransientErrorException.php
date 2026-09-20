<?php

namespace App\Exceptions;

/**
 * GitHub reported a transient failure: a 5xx response or a network-level
 * connection failure. No retry/backoff is attempted here; the ingestion
 * run ends explicitly as failed so a later manual or scheduled sync can
 * try again.
 */
class GitHubTransientErrorException extends GitHubClientException
{
    public static function forRepository(string $repository, string $detail): self
    {
        return new self("GitHub reported a transient failure ({$detail}) while reading [{$repository}].");
    }

    public function failureCode(): string
    {
        return 'github_transient_error';
    }
}
