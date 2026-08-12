<?php

namespace App\Http\Middleware;

use App\Models\Plugin;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePluginPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $plugin = $request->route('plugin');

        if (! $plugin instanceof Plugin) {
            $plugin = Plugin::query()->whereKey($plugin)->first();
        }

        if ($plugin === null) {
            throw new AuthorizationException('Plugin scope is required.');
        }

        $plugin->loadMissing('community');

        if (! $request->user()?->hasCommunityPermission($plugin->community, $permission)) {
            throw new AuthorizationException('You do not have permission for this plugin.');
        }

        return $next($request);
    }
}
