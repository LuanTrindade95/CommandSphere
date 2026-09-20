<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Generates and validates correlation IDs used to trace a single logical
 * operation (HTTP request, webhook delivery, scheduled sync iteration)
 * across ingestion runs, queued jobs, and structured log events.
 *
 * A client-supplied `X-Request-Id` is only trusted when it matches a safe,
 * bounded character set. This prevents log injection (no control
 * characters, no newlines) and unbounded input; anything else is replaced
 * with a freshly generated ID rather than echoed back.
 */
final class CorrelationId
{
    private const PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

    public const HEADER = 'X-Request-Id';

    public const REQUEST_ATTRIBUTE = 'correlation_id';

    /**
     * Produce a new, safe-by-construction correlation ID.
     */
    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Determine whether a client-supplied value is safe to reuse as-is.
     */
    public static function isValid(?string $value): bool
    {
        return $value !== null && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Resolve the correlation ID assigned to the current request by
     * `App\Http\Middleware\AssignCorrelationId`, generating one as a
     * fallback for call sites reached outside that middleware (e.g. Artisan
     * commands, unit tests exercising services directly).
     */
    public static function fromRequest(Request $request): string
    {
        $assigned = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (is_string($assigned) && $assigned !== '') {
            return $assigned;
        }

        return self::generate();
    }
}
