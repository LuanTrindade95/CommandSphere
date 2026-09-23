<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    /**
     * Restrictive CSP for API/JSON responses.
     *
     * The backend only serves JSON (plus the unused Laravel welcome view and
     * the health check), so no script, style, image, or connection source
     * needs to be allowed. `default-src 'none'` denies every fetch directive;
     * `base-uri`, `frame-ancestors`, and `form-action` are pinned to `'none'`
     * explicitly because they do not inherit from `default-src`.
     */
    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "form-action 'none'",
        ]);
    }
}
