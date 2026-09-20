<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Emits structured, JSON-formatted domain events to the dedicated
 * `telemetry` log channel, keyed by a stable event name from the F-011
 * taxonomy (see `brain/audits/2026-06-13-system-audit.md`).
 *
 * This channel is additive: it never touches the default `stack`/`single`
 * channels used for framework/application logging. Context values must
 * already be safe to log (identifiers, codes, counts); this class does not
 * accept raw request payloads, headers, or Markdown content.
 */
final class Telemetry
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function event(string $name, array $context = []): void
    {
        Log::channel('telemetry')->info($name, array_filter(
            $context,
            static fn (mixed $value): bool => $value !== null,
        ));
    }
}
