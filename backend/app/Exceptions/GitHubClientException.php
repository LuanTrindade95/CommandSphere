<?php

namespace App\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Base type for every failure a GitHubClient implementation can raise.
 *
 * IngestionService::run() catches this single type so that any GitHub
 * domain failure ends the ingestion run explicitly as "failed" with a
 * stable log code, instead of leaving the run stuck in "running" or
 * silently succeeding with no documents.
 */
abstract class GitHubClientException extends RuntimeException
{
    /**
     * Stable, log-safe code identifying the failure category.
     *
     * Defaults to the snake_cased class name so existing exception types
     * keep their historical codes; new categories override this with an
     * explicit, cleaner literal.
     */
    public function failureCode(): string
    {
        return Str::snake(class_basename($this));
    }
}
