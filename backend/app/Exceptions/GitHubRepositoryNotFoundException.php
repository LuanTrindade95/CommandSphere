<?php

namespace App\Exceptions;

class GitHubRepositoryNotFoundException extends GitHubClientException
{
    public static function forRepository(string $repository): self
    {
        return new self("GitHub repository [{$repository}] was not found.");
    }
}
