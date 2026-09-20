<?php

namespace App\Exceptions;

/**
 * The GitHub API rejected the request as unauthenticated or unauthorized
 * (HTTP 401, or a 403 that does not carry a rate-limit signal).
 */
class GitHubAuthenticationException extends GitHubClientException
{
    public static function forRepository(string $repository, int $status): self
    {
        return new self("GitHub authentication or permission check failed (HTTP {$status}) while reading [{$repository}].");
    }

    public function failureCode(): string
    {
        return 'github_authentication_failed';
    }
}
