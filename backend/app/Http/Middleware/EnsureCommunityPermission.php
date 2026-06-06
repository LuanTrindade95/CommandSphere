<?php

namespace App\Http\Middleware;

use App\Models\Community;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommunityPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $community = $request->route('community');

        if (! $community instanceof Community) {
            $community = Community::query()->whereKey($community)->first();
        }

        if ($community === null) {
            throw new AuthorizationException('Community scope is required.');
        }

        if (! $request->user()?->hasCommunityPermission($community, $permission)) {
            throw new AuthorizationException('You do not have permission for this community.');
        }

        return $next($request);
    }
}
