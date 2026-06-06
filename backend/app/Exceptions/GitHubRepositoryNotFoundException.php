<?php

namespace App\Exceptions;

use RuntimeException;

class GitHubRepositoryNotFoundException extends RuntimeException
{
    public static function forRepository(string $repository): self
    {
        return new self("GitHub repository [{$repository}] was not found.");
    }
}
