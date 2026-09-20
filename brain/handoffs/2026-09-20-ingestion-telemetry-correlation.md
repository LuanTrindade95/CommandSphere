# Handoff - Ingestion Telemetry Correlation

Date: 2026-09-20
Branch: `feature/ingestion-telemetry-correlation`

## Scope

Audited remediation item C5: F-011, correlation IDs and structured events for the ingestion flow. Depends on the ADR-27 failure-code taxonomy, which `ingestion.failed` consumes through the existing `code` key.

## Implemented

- `App\Support\CorrelationId` validates and generates IDs; `App\Http\Middleware\AssignCorrelationId` applies it to every API request and echoes the ID in the `X-Request-Id` response header.
- Additive migration `2026_09_20_000001_add_correlation_id_to_ingestion_runs_table.php`: nullable, indexed `correlation_id`.
- `IngestionService::start()` accepts an optional correlation ID and generates one when absent. `PluginSyncController`, `GitHubWebhookController`, and `SyncScheduledPlugins` pass theirs; scheduled sync generates one per run.
- Every `IngestionRun.log` entry carries `correlation_id` as a field.
- `App\Support\Telemetry::event()` writes JSON events to the additive `telemetry` channel. Events and fields are listed in ADR-29.
- Tests: `backend/tests/Unit/CorrelationIdTest.php`, `backend/tests/Feature/IngestionTelemetryTest.php`.

## Not changed

The default log channel, the webhook HMAC check, `IngestionService::fail()` signature, the `IngestionRun.log` entry keys other than the added field, the `wasRecentlyCreated`/`partial`/`failed` semantics, and every pre-existing test expectation.

## Validation

- First independent audit: REJECTED on hostile input. The validation pattern used a bare `$`, so a `X-Request-Id` ending in `\n` was echoed raw in the response header and persisted in the run column. Fixed in `a388496` with the PCRE `D` modifier plus regression cases for trailing `\n`, `\r`, and `\r\n`.
- Second independent audit: APPROVED. The auditor rebuilt the image, matched file hashes inside and outside the container, and re-ran every roadmap item with its own probe: end-to-end propagation for webhook, manual, and scheduled sync; seven hostile `X-Request-Id` values replaced by UUIDs; `ingestion.failed` with `github_transient_error` on a forced 500; valid JSON for all six events; no token, `Authorization`, `Bearer`, signature, secret, or Markdown canary in `telemetry.log`, `laravel.log`, or run logs; pre-migration runs returning 200 with `correlation_id: null`.
- Gates on base `25bbb3c`: Pest 64 passed, Pint 128 files, no `skip`.
- Combined suite after merging `origin/main` at `29a7881` (server-side sanitization): `PENDING` locally because Docker Desktop stopped responding during the run; the merge had no conflicts and touched no C5 file. The pull request CI is the gate of record for the combined suite.

## Environment traps

- The `backend` service has no bind mount. Rebuild with `docker compose up -d --build backend` and compare file hashes before trusting any gate.
- Several worktrees run their own Compose projects on this machine. Port `8000` can already be allocated by another worktree's stack; gates can run in an unpublished container with `docker compose run --rm --no-deps backend ...` against this project's own dependencies. Another session's containers are never stopped to free the port.
- `origin/main` advanced twice while C5 was in progress. Compare diffs against `git merge-base origin/main HEAD`, not against `origin/main`, or work merged by other branches shows up as false deletions.

## Open

- Propagate the correlation ID to the GitHub call, Meilisearch indexing, and `CommandIndexUpdated`.
- Remaining taxonomy events (`plugin.created`, `search.executed`, `realtime.broadcast.failed`, and others) and duration/latency metrics.
- Retention and rotation for `storage/logs/telemetry.log`, which currently uses a plain `StreamHandler`.
- `composer audit` was not part of this item's verification.
