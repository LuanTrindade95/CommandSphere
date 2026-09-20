<?php

namespace App\Exceptions;

/**
 * GitHub returned a response whose payload cannot be trusted: a
 * non-JSON body, a missing tree, an unsupported content encoding, or
 * invalid base64 content. The ingestion run fails closed instead of
 * silently producing an empty or partial result.
 */
class GitHubMalformedResponseException extends GitHubClientException
{
    public static function forRepository(string $repository, string $detail): self
    {
        return new self("GitHub returned a malformed response ({$detail}) while reading [{$repository}].");
    }

    public function failureCode(): string
    {
        return 'github_malformed_response';
    }
}
