<?php

namespace App\Exceptions;

/**
 * GitHub reported the git tree as truncated. Treating a truncated tree as
 * complete would silently skip documents, so the run fails closed with a
 * dedicated code instead of folding into the generic malformed-response
 * category.
 */
class GitHubTreeTruncatedException extends GitHubMalformedResponseException
{
    public static function forRepository(string $repository, string $detail = 'tree was truncated'): self
    {
        return new self("GitHub returned a malformed response ({$detail}) while reading [{$repository}].");
    }

    public function failureCode(): string
    {
        return 'github_tree_truncated';
    }
}
