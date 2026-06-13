# Handoff - Analytics Bounds

Date: 2026-06-13
Branch: `fix/analytics-bounds`

## Scope

Build-loop remediation phase for audit finding F-005.

## Implemented

- Added `COMMANDSPHERE_ANALYTICS_MAX_DAYS` with default `365`.
- `/api/v1/analytics/most-viewed` validates `days` as optional integer with minimum `1` and configured maximum.
- Invalid, negative, zero, non-numeric, or excessive periods now return `422 validation.failed`.
- Analytics metadata now reports accepted `days`, configured `max_days`, and `period_started_at`.
- Frontend analytics response type now includes the new metadata fields.
- Command-view dedupe test now uses Scout `null` because Meilisearch indexing is not the behavior under test.

## Validation

- `vendor\bin\pint.bat app\Http\Controllers\Api\V1\AnalyticsController.php config\commandsphere.php tests\Feature\DiscoveryApiTest.php` passed.
- `npm.cmd run lint` passed.
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor\bin\pest.bat tests\Feature\DiscoveryApiTest.php --filter="analytics|command views" --colors=never` passed: 3 tests, 19 assertions.

## Remaining Work

- Structured telemetry, correlation IDs, and operational event taxonomy remain open under F-011.
