# Next Actions

Updated: 2026-09-23

This roadmap is intentionally product-oriented. New work should strengthen portfolio signal, production realism, and architecture maturity rather than adding generic CRUD.

## Priority 1 - Session Strategy Upgrade

Problem: Sanctum bearer token is memory-only. This is safer than browser storage, but refresh loses session and weakens product UX.

Recommended direction:

- Evaluate httpOnly cookie/BFF-style session for the Angular SSR frontend.
- Preserve SSR isolation and avoid leaking user state into rendered HTML.
- Keep dev-login available only for local/testing.

Acceptance criteria:

- Refresh preserves authenticated session without localStorage/sessionStorage token storage.
- SSR output does not contain bearer tokens or private user data.
- Auth guards, interceptors, logout, and Reverb auth continue to work.
- Backend and frontend tests cover success, expired session, logout, and invalid auth.

Risks:

- Cross-origin cookie configuration can complicate local Docker and production deployments.
- SSR request isolation must be rechecked.

## Priority 2 - Production Runtime Configuration Hardening

Problem: The browser API base URL is hardcoded to `http://localhost:8000/api/v1`, while production Compose only configures the SSR server-side API URL. Reverb runtime config also relies on local defaults/localStorage.

Status: Implemented in branch `fix/runtime-production-config` and production-proven. The Docker production hydrated-browser smoke ran on 2026-09-23 against `docker-compose.prod.yml` with public values that differ from the defaults, under adversarial audit, and closed the last validation item. See `brain/handoffs/2026-09-23-production-hydrated-smoke.md`.

Recommended direction:

- Introduce explicit public runtime configuration for browser API origin, Reverb host, Reverb scheme, Reverb port, allowed SSR hosts, and public canonical origin.
- Prefer a same-origin reverse proxy (`/api/v1`, websocket path) when possible.
- Stop deriving production canonical/JSON-LD URLs from raw request `Host`.

Acceptance criteria:

- Production-mode browser calls do not point to localhost. `VALIDATED` through SSR artifact smoke with public runtime config.
- SSR uses the configured production origin for canonical, Open Graph, and JSON-LD metadata. `VALIDATED` through SSR artifact smoke.
- Reverb uses documented production runtime config. `VALIDATED` in the production stack: the browser opens `ws://<public host>:<public port>/app/<key>` from the served runtime config, and Reverb answers `pusher:connection_established`. The public variable wins over the fixed `COMMANDSPHERE_REVERB_HOST: localhost` in the `frontend` service.
- Docker production smoke validates hydrated browser API calls. `VALIDATED`: 53 post-hydration requests, every API call on the configured origin, zero requests to the default origin, which was bound away and answered connection refused.
- SSR ignores a forged `Host` header for canonical and metadata. `VALIDATED`: `publicUrlFor()` in `frontend/src/server.ts` builds from `COMMANDSPHERE_PUBLIC_ORIGIN` only.
- Production `dev-login` stays disabled. `VALIDATED`: `403 auth.dev_login_disabled`, refused before request validation.

Risks:

- Misaligned backend/frontend origins can break OAuth callback, broadcast auth, and CORS if introduced without an integration test.

## Priority 3 - OAuth State And Auth Hardening

Problem: Discord OAuth currently uses `stateless()`, which removes OAuth `state` verification and weakens login CSRF/account-injection protection.

Status: Implemented in branch `fix/oauth-state-hardening`. The implementation keeps Socialite stateless for API compatibility but adds first-party signed state validation through a short-lived httpOnly cookie.

Recommended direction:

- Add signed state for Discord OAuth.
- Store state in short-lived httpOnly cookie or server-side cache.
- Bind state to a safe frontend return URL.
- Add invalid/missing/expired/reused state tests.

Acceptance criteria:

- OAuth callback rejects missing or invalid state. `VALIDATED` by `AuthApiTest`.
- Valid Discord login still creates/updates the user and attaches default community membership. `VALIDATED` by `AuthApiTest`.
- Auth flow remains SSR-safe and does not persist bearer tokens in browser storage.

Risks:

- Cookie settings must be coordinated with the session strategy and deployment origin.

## Priority 4 - CI And Service-Backed Test Hardening

Problem: CI currently does not define MySQL, Redis, or Meilisearch services, while important tests and product claims depend on those systems.

Status: CI workflow updated in branch `fix/ci-service-gates`. Final proof is pending the next GitHub Actions run because local validation cannot execute GitHub service containers.

Recommended direction:

- Split fast unit tests from service-backed integration tests, or add required CI services.
- Add `composer audit`, `npm audit --audit-level=critical`, and Playwright where feasible.
- Ensure Meilisearch tests fail fast when the service is missing.

Acceptance criteria:

- CI proves backend tests, frontend tests, SSR build, dependency audits, and service-backed search behavior. `VALIDATED` by run `35485832903` on `main` (`8623fea`), both jobs `success`.
- CI logs clearly distinguish skipped, pending, and executed integration checks.

Risks:

- CI runtime will increase. Keep jobs parallel and cache dependencies.

## Priority 5 - Ingestion Operations Hardening

Problem: Manual sync, scheduled sync, and webhooks can enqueue overlapping runs for the same plugin version. Ingestion run listing also loads all runs before permission filtering.

Status: Implemented in branch `fix/ingestion-run-controls` for active-run dedupe, run source tracking, SQL-scoped listing, and pagination. Retry semantics remain a future enhancement.

Recommended direction:

- Add per-plugin-version uniqueness/locking for queued and running ingestion jobs.
- Add retry semantics that create new auditable runs.
- Scope ingestion run queries in SQL and paginate the admin endpoint.
- Record run source: manual, webhook, scheduled.

Acceptance criteria:

- Duplicate enqueue attempts do not create overlapping jobs unless explicitly forced. `VALIDATED` by `AutomationRealtimeTest`.
- Admin ingestion list is paginated and scoped in the query. `VALIDATED` by `AutomationRealtimeTest`.
- Realtime updates and historical run detail still work.

Risks:

- Existing run history should remain readable after adding source/lock metadata.

## Priority 6 - Analytics Bounds And Telemetry

Problem: Analytics period is unbounded and system telemetry is mostly implicit in `IngestionRun.log`.

Status: Analytics period bounds implemented in branch `fix/analytics-bounds`. Correlation IDs and structured ingestion/webhook events implemented in branch `feature/ingestion-telemetry-correlation` (ADR-29). Remaining: propagate the ID to the GitHub call, Meilisearch indexing, and `CommandIndexUpdated`; add the other taxonomy events and duration/latency metrics; define retention for `storage/logs/telemetry.log`.

Recommended direction:

- Validate analytics `days` with a product cap.
- Add structured operational events and correlation IDs.
- Track webhook -> run -> job -> GitHub -> Meilisearch -> broadcast flow.

Acceptance criteria:

- Analytics rejects invalid periods and caps expensive windows. `VALIDATED` by `DiscoveryApiTest`.
- Logs/events include correlation IDs and relevant domain IDs. `VALIDATED` for request -> run -> job -> run log -> telemetry events by `IngestionTelemetryTest`, `CorrelationIdTest`, and an independent audit.
- Ingestion run detail can be debugged without server-log archaeology.

Risks:

- Avoid logging secrets, bearer tokens, raw webhook signatures, or full Markdown content.

## Priority 7 - Version Comparison For Commands And Docs

Problem: The domain already models plugin versions, but the product does not yet showcase version diff workflows.

Recommended direction:

- Add version comparison between documents and commands.
- Start with read-only diffs for command metadata and Markdown document sections.
- Keep diff computation bounded and cacheable.

Acceptance criteria:

- User can compare two versions of the same plugin.
- Added, removed, and changed commands are visible.
- Document diff view is readable and responsive.
- Backend avoids N+1 queries and excessive memory use on large documentation sets.

Risks:

- Markdown HTML diff can become noisy. Prefer structured command diff first, then document section diff.

## Priority 8 - Ingestion Observability And Retry Controls

Problem: Ingestion runs have status, stats, log, and realtime events, but operational troubleshooting can be richer.

Recommended direction:

- Add retry action for failed/partial runs with permission checks.
- Surface warnings by document and severity.
- Add duration, source commit/ref, changed document count, and skipped unchanged count.
- Consider a run detail timeline.

Acceptance criteria:

- Admin can understand why a run was partial or failed without reading server logs.
- Retry creates a new auditable run instead of mutating history.
- Realtime updates still stream to private community channels.
- Tests cover permission denial, retry success, and retry failure path.

Risks:

- Avoid exposing repository internals or secrets in logs.

## Priority 9 - Documentation Quality Score

Problem: CommandSphere can ingest docs, but does not yet help maintainers improve them.

Recommended direction:

- Compute quality indicators during parsing:
  - missing command syntax
  - duplicate command slug
  - missing description
  - missing aliases/parameters when convention expects them
  - malformed frontmatter
- Present plugin-level and document-level health indicators.

Acceptance criteria:

- Warnings become actionable quality signals.
- Scores are deterministic and derived from parser output.
- UI communicates issues without looking like a generic admin table.

Risks:

- Do not overfit quality rules to a single plugin ecosystem. Keep rules documented and extensible.

## Priority 10 - Public Plugin Onboarding Flow

Problem: Admin plugin creation exists, but product storytelling would improve with a guided onboarding flow.

Recommended direction:

- Add a polished multi-step flow for adding a plugin repository.
- Validate GitHub repository format, docs path, default branch, and initial version metadata.
- Preview detected Markdown files before enqueueing ingestion.

Acceptance criteria:

- Maintainer can add a plugin with clear validation and preview.
- Invalid GitHub inputs fail with actionable messages.
- No sync runs until the user explicitly confirms.
- Permission and community scope remain enforced.

Risks:

- GitHub API calls may be slow or rate-limited; use loading states and retry-safe behavior.

## Priority 11 - Production Deployment Runbook

Problem: Docker production packaging exists, but a deploy runbook would make the portfolio more credible.

Recommended direction:

- Document target deployment topology.
- Define environment variables, secret rotation, queue/reverb process management, migrations, Meilisearch index sync, backups, and rollback.
- Add health checks and smoke scripts if useful.

Acceptance criteria:

- A reviewer can understand how this would run in production.
- Operational risks and rollback steps are explicit.
- Documentation matches existing Docker files and env variables.

Risks:

- Avoid pretending a provider-specific deployment exists unless it has been validated.

## Priority 12 - Dependency Advisory Follow-Up

Problem: CI is green, but part of the dependency risk is deliberately carried instead of fixed. Two overrides exist as a maintenance point, and the Backend green on CI is weaker than the local green.

Recommended direction:

- Re-evaluate the `pacote` and `tar` overrides in `frontend/package.json` at every Angular toolchain upgrade and drop them once the official chain resolves `tar` 7.x (ADR-26).
- Close `@sigstore/sign` and `@sigstore/verify`, the only remaining advisories reporting `fixAvailable: true`.
- Plan the Angular major upgrade that closes the remaining runtime/toolchain advisories (`@angular/*@22.x`, `@angular-devkit/build-angular@21.2.24`+), as a task of its own.
- Investigate the Pest warnings on the GitHub runner: `32 warnings, 1 passed` there versus `33 passed (191 assertions)` in the dev container, caused by `file_get_contents` on runner paths.
- Update the deprecated `actions/checkout@v4` and `actions/setup-node@v4`, and plan the `ubuntu-latest` migration to Ubuntu 26 starting October 19, 2026.

Acceptance criteria:

- Overrides are either still justified by recorded facts or removed.
- Backend Pest reports the same passing count on CI and locally, with no warnings masking the result.
- Audit gates stay at `--audit-level=critical` with no weakening.

Risks:

- An Angular major upgrade touches SSR, build and tests at once; it needs its own branch, gates and audit.

## Priority 13 - Seeding Is Broken In The Production Image

Problem: `fakerphp/faker` is declared only in `require-dev` in `backend/composer.json`, and `docker/backend.prod.Dockerfile` installs with `composer install --no-dev`. Every factory that calls `fake()`, starting at `backend/database/factories/UserFactory.php`, is therefore undefined in the production image, so `php artisan migrate --seed` and `php artisan db:seed` abort with `Call to undefined function Database\Factories\fake()`.

Reproduced twice and independently on 2026-09-23, in the smoke and in the audit.

Recommended direction:

- Decide whether the production image is supposed to seed at all. If it is, move `fakerphp/faker` to `require`, or split demo factories from the production-facing seeder so that the production path has no Faker dependency.
- Keep the split explicit, so a demo or portfolio dataset never becomes a production seeding requirement by accident.

Acceptance criteria:

- Seeding either succeeds in the `--no-dev` image or is explicitly documented as unsupported there, with the supported path written down.
- The production Docker smoke can create a dataset without direct SQL inserts.

Risks:

- Moving Faker to `require` ships a development library in the production image. Splitting the seeders is more work but keeps the image lean.

## Backlog

- Review the API `Access-Control-Allow-Origin: *`. The 2026-09-23 production smoke saw it on `/api/v1/auth/dev-login`, which means `config/cors.php` is unpublished and every origin is allowed. That smoke predates the CSP work merged in ADR-30, so confirm the header on current `main` before acting.
- Isolate the backend test suite from the development database. Feature tests use `RefreshDatabase`, `backend/phpunit.xml` keeps its `DB_CONNECTION`/`DB_DATABASE` overrides commented out, and no `backend/.env.testing` exists, so the documented Pest gate wipes `commandsphere` on every run. Point the suite at a dedicated test database, as CI already does with `commandsphere_test`, and document how to create it locally.
- Fix `portfolio-happy-paths.spec.ts` › `admin sincroniza e vê ingestion run`, the only failing E2E test: it asserts `/Run #/` while the pt-BR UI renders `Execução #` from the `runId` key in `frontend/public/i18n/pt-BR.json`.
- Make the Pest assertion total deterministic. `it indexes commands in meilisearch through scout import` makes between 8 and 10 assertions depending on the run, so the suite total varies on unchanged code; the test count is stable.
- Decide whether `X-Request-Id` must appear on API responses for requests that match no route. `AssignCorrelationId` lives in the `api` middleware group, so 404 and 405 responses for unrouted paths carry no correlation ID, while the global `SecurityHeaders` still applies.
- On Windows worktrees, Jest loads `frontend/e2e/*.spec.ts` despite `testPathIgnorePatterns: ['<rootDir>/e2e/']` and reports 2 failed suites with 0 failed tests.
- Fix the two `runtime-config.spec.ts` Jest failures caused by Docker Compose environment variables leaking into the test `process.env`.
- Decide F-015: public discovery endpoints accept an expired bearer token as its owner, because they resolve it through `PersonalAccessToken::findToken` outside the `auth:sanctum` guard and `sanctum.expiration` is null. Closing it changes endpoint responses, so it needs its own branch, its own decision record, and an update to the characterization test that currently locks the behavior.
- Keyboard-first power-user UX for command palette actions.
- Saved searches and team-level curated collections.
- Export command/document references.
- Accessibility audit and fixes with Playwright or axe integration.
- API pagination standardization for large communities.
- More granular audit events for admin actions.
- Advanced ranking using views, recency, plugin popularity, and exact syntax match.
- Visual regression baseline for portfolio screenshots.
