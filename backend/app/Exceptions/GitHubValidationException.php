<?php

namespace App\Exceptions;

/**
 * The GitHub API rejected the request as a conflict or validation failure
 * (HTTP 409 or 422). Retrying with the same request will not help.
 */
class GitHubValidationException extends GitHubClientException
{
    public static function forRepository(string $repository, int $status): self
    {
        return new self("GitHub rejected the request as a conflict or validation failure (HTTP {$status}) while reading [{$repository}].");
    }

    public function failureCode(): string
    {
        return 'github_validation_failed';
    }
}
