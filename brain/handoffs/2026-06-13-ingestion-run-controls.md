# Handoff - Ingestion Run Controls

Date: 2026-06-13
Branch: `fix/ingestion-run-controls`

## Scope

Build-loop remediation phase for audit findings F-004 and F-007.

## Implemented

- Added `source` to `ingestion_runs` with values produced by manual sync, webhook sync, and scheduled sync.
- `IngestionService::start()` now returns an existing active `queued/running` run for the same plugin version instead of creating an overlapping run.
- Manual sync, webhooks, and scheduled sync only dispatch a job when a new run is created.
- `RunPluginVersionIngestion` implements `ShouldBeUnique` with a per-plugin-version unique id.
- Admin ingestion list now filters by viewable community IDs in SQL before fetching runs.
- Admin ingestion list now paginates and returns `meta.current_page`, `meta.last_page`, `meta.per_page`, and `meta.total`.
- Ingestion pipeline tests now use Scout `null` where Meilisearch is not part of the behavior under test.

## Validation

- `vendor\bin\pint.bat ...` passed for touched backend files.
- `npm.cmd run lint` passed for the frontend API model contract change.
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor\bin\pest.bat tests\Feature\AutomationRealtimeTest.php --colors=never` passed.
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor\bin\pest.bat tests\Feature\IngestionPipelineTest.php --colors=never` passed.
- Combined backend targeted run passed: 11 tests, 54 assertions.

## Remaining Work

- Retry controls for failed/partial runs remain a future ingestion observability enhancement.
- Full MySQL/Redis/Meilisearch proof is delegated to the service-backed CI gate.
