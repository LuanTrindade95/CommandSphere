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
 * - A resolvable, non-expired bearer token returns its owner.
 * - A bearer token that fails to resolve, or that resolves but has an
 *   `expires_at` in the past, returns an empty, unpersisted `User`
 *   instance instead of `null`, so an authenticated-looking but invalid
 *   or lapsed request is never widened to the anonymous public scope
 *   (see ADR-23, F-015). A token without `expires_at` never lapses here.
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

        if ($accessToken?->expires_at?->isPast()) {
            // Do not widen an authenticated-looking, lapsed request to the anonymous public scope.
            return new User;
        }

        $tokenable = $accessToken?->tokenable;

        if ($tokenable instanceof User) {
            return $tokenable;
        }

        // Do not widen an authenticated-looking request to the anonymous public scope.
        return $request->headers->has('Authorization') ? new User : null;
    }
}
