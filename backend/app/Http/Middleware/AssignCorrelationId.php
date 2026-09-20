<?php

namespace App\Http\Middleware;

use App\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns every API request a correlation ID: a valid client-supplied
 * `X-Request-Id` is reused, otherwise one is generated. The ID is exposed
 * to downstream code via the request attribute bag and echoed back on the
 * response so a caller can correlate their request with server-side logs
 * and ingestion runs.
 */
class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(CorrelationId::HEADER);
        $correlationId = CorrelationId::isValid($incoming) ? $incoming : CorrelationId::generate();

        $request->attributes->set(CorrelationId::REQUEST_ATTRIBUTE, $correlationId);

        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }
}
