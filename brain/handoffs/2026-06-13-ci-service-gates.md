# Handoff - CI Service Gates

Date: 2026-06-13
Branch: `fix/ci-service-gates`

## Scope

Build-loop remediation phase for audit finding F-003.

## Implemented

- Added MySQL 8 service container to backend CI.
- Added Redis 7 service container to backend CI.
- Added Meilisearch v1.12 service container to backend CI.
- Added bounded Meilisearch readiness wait before backend install/test steps.
- Added explicit Pest environment variables for database, Meilisearch, queue, cache, mail, session, and app key.
- Added `composer audit` to backend CI.
- Added `npm audit --audit-level=critical` to frontend CI.

## Local Validation

- `composer audit` passed with no advisories.
- `npm audit --audit-level=critical` completed without critical advisories. Existing moderate/high Angular toolchain advisories remain.
- `git diff --check` passed.

## Pending Validation

- Next GitHub Actions run must prove the service-backed Pest suite against MySQL, Redis, and Meilisearch.
- If CI runtime grows too much, split backend into fast SQLite and service-backed integration jobs.
