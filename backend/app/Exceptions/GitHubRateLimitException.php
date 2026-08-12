<?php

namespace App\Exceptions;

use RuntimeException;

class GitHubRateLimitException extends RuntimeException
{
    public static function forRepository(string $repository): self
    {
        return new self("GitHub API rate limit reached while reading [{$repository}].");
    }
}
