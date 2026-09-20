<?php

namespace App\Exceptions;

class GitHubRateLimitException extends GitHubClientException
{
    public static function forRepository(string $repository): self
    {
        return new self("GitHub API rate limit reached while reading [{$repository}].");
    }
}
