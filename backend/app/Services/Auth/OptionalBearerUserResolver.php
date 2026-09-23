<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resolves the optional acting user for public discovery endpoints
 * (catalog and search) that must stay readable anonymously.
 *
 * Behavior (F-013 characterization, not to be changed without a decision
 * recorded in docs/DECISIONS.md):
 * - A user already authenticated by the guard is returned as-is.
 * - A resolvable bearer token returns its owner.
 * - A bearer token that fails to resolve returns an empty, unpersisted
 *   `User` instance instead of `null`, so an authenticated-looking but
 *   invalid request is never widened to the anonymous public scope
 *   (see ADR-23).
 * - No bearer token at all (including a header that is not a `Bearer`
 *   scheme, or an empty `Bearer` value) returns `null`, the anonymous
 *   public scope.
 */
class OptionalBearerUserResolver
{
    public function resolve(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $tokenable = $accessToken?->tokenable;

        if ($tokenable instanceof User) {
            return $tokenable;
        }

        // Do not widen an authenticated-looking request to the anonymous public scope.
        return $request->headers->has('Authorization') ? new User : null;
    }
}
