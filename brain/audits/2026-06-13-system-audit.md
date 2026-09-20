# System Audit - 2026-06-13

Scope: broad audit of CommandSphere v1 across security, telemetry, business rules, backend logic, frontend/SSR, tests, CI, and operational readiness.

This is a documentation audit. No product code was changed.

Remediation update: F-001, F-010, and F-012 were addressed in branch `fix/runtime-production-config` after this audit. The fix introduced public runtime configuration for browser API/Reverb, configured SSR allowed hosts/public origin, and normalized canonical/Open Graph/JSON-LD metadata in the generated SSR HTML. Full production Docker/E2E validation remains a follow-up gate.

Remediation update: F-002 was addressed in branch `fix/oauth-state-hardening`. The fix added a first-party Discord Socialite provider, signed short-lived OAuth state cookie generation, callback state validation, state cookie cleanup, and Auth API tests for redirect, rejection, and valid callback.

Remediation update: F-003 was configured in branch `fix/ci-service-gates`. The CI workflow now provisions MySQL, Redis, and Meilisearch services, runs backend/frontend dependency audits, and executes Pest with explicit testing service environment variables. Final validation depends on the next GitHub Actions run.

Remediation update: F-004 and F-007 were addressed in branch `fix/ingestion-run-controls`. The fix scopes ingestion run listing in SQL, adds pagination metadata, records run source, reuses active queued/running runs, and makes ingestion jobs unique per plugin version.

Remediation update: F-005 was addressed in branch `fix/analytics-bounds`. The analytics endpoint now validates `days`, caps the period with `COMMANDSPHERE_ANALYTICS_MAX_DAYS`, and returns the accepted period in metadata.

## Executive Summary

CommandSphere already has a stronger baseline than a typical portfolio project: scoped permissions, HMAC webhooks, private realtime channels, Markdown sanitization in the Angular viewer, rate limits on key public/operational endpoints, idempotent ingestion tests, and documented architecture decisions.

The largest gaps are not in basic CRUD correctness. They are production-hardening gaps:

- browser-side API/Reverb configuration is still local-dev biased;
- Discord OAuth uses stateless flow without `state` protection;
- CI does not prove the same service-backed surfaces claimed in the README;
- ingestion and analytics endpoints need pagination, bounds, and queue/idempotency controls;
- telemetry is mostly implicit through DB records, not structured operational observability.

## Severity Legend

- Critical: direct exploitable compromise or production outage with no reasonable mitigation.
- High: likely production breakage, meaningful security weakness, or serious multi-tenant/performance risk.
- Medium: important hardening, correctness, or scalability risk.
- Low: polish, maintainability, or portfolio credibility issue.

## Findings

### F-001 - Browser API Base URL Is Hardcoded To Localhost

Severity: High

Status: Mitigated in `fix/runtime-production-config`.

Evidence:

- `frontend/src/app/core/api/api.tokens.ts:17` returns `http://localhost:8000/api/v1` for the browser.
- `docker-compose.prod.yml` only sets `COMMANDSPHERE_API_INTERNAL_URL` for SSR server-side calls.

Impact:

In a real production deployment, the user's browser would call `localhost:8000` on the user's machine instead of the deployed backend. Public SSR can render, but client-side navigation, auth, favorites, admin actions, realtime auth, and search refreshes can fail after hydration.

Recommended fix:

- Add a browser-safe runtime config mechanism for the public API origin.
- Prefer same-origin `/api/v1` behind a single reverse proxy, or inject a public `COMMANDSPHERE_API_PUBLIC_URL` into a generated runtime config file.
- Add a production-mode smoke/E2E that verifies the hydrated browser calls the deployed API origin, not localhost.

Priority:

Fix before any production-like deployment claim.

### F-002 - Discord OAuth Uses Stateless Flow Without State/CSRF Protection

Severity: High

Status: Mitigated in `fix/oauth-state-hardening`.

Evidence:

- `backend/app/Http/Controllers/Api/V1/Auth/DiscordAuthController.php:19`
- `backend/app/Http/Controllers/Api/V1/Auth/DiscordAuthController.php:26`

Impact:

`stateless()` removes OAuth `state` verification. This simplifies API-style OAuth but weakens protection against login CSRF/account injection. A user could be tricked into completing a callback that logs them into the attacker's Discord-linked account depending on frontend flow and callback handling.

Recommended fix:

- Move to stateful OAuth with signed state stored in a short-lived httpOnly cookie or server-side cache.
- Bind state to the intended frontend return URL.
- Add tests for missing, invalid, reused, and expired state.

Priority:

Fix before public OAuth is used outside local/demo scenarios.

### F-003 - CI Does Not Prove Service-Backed Claims

Severity: High

Status: Configured in `fix/ci-service-gates`; pending remote CI execution.

Evidence:

- `.github/workflows/ci.yml` runs backend Pest but defines no MySQL, Redis, or Meilisearch service containers.
- `backend/tests/Feature/DiscoveryApiTest.php` explicitly configures Scout to Meilisearch and imports commands.
- README claims Meilisearch, Reverb/Horizon, Docker, E2E, and audits as part of quality evidence.

Observed during this audit:

- `composer audit` passed.
- frontend typecheck, Jest, and build passed.
- backend Pest timed out after 180 seconds in the local PowerShell run, so the full backend suite was not validated in this audit.

Impact:

The pipeline may not catch failures in the highest-risk surfaces: Meilisearch settings, DB-backed authorization, queues, and realtime integration. If CI was green previously, it may have depended on local/manual validation rather than reproducible CI services.

Recommended fix:

- Add CI services for MySQL, Redis, and Meilisearch, or split tests into pure SQLite tests and explicit service-backed integration jobs.
- Add `composer audit`, `npm audit --audit-level=critical`, and optionally Playwright as separate CI jobs.
- Make service-backed tests fail fast with clear missing-service messages.

Priority:

Fix before relying on CI for release confidence.

### F-004 - Ingestion Runs Are Loaded Globally Then Filtered In Memory

Severity: Medium-High

Status: Mitigated in `fix/ingestion-run-controls`.

Evidence:

- `backend/app/Http/Controllers/Api/V1/IngestionController.php:19`
- `backend/app/Http/Controllers/Api/V1/IngestionController.php:22`

Impact:

The endpoint loads all ingestion runs with plugin/community relations, then filters each row through `canView()`. This does not expose unauthorized rows in the response, but it creates avoidable multi-tenant blast radius: slow requests, memory growth, and noisy authorization checks as run history grows.

Recommended fix:

- Push community scope into the database query.
- Add pagination and ordering limits.
- Add tests proving users only retrieve runs from communities where they have `analytics.view` or `ingestion.run`.

Priority:

Fix before ingestion history becomes large or multi-community usage expands.

### F-005 - Analytics Period Is Unbounded

Severity: Medium

Status: Mitigated in `fix/analytics-bounds`.

Evidence:

- `backend/app/Http/Controllers/Api/V1/AnalyticsController.php:59`

Impact:

`days` is cast from the request without explicit min/max validation. Large values can force broad scans over `command_views`; negative values produce confusing future windows. This is authenticated, but still an avoidable performance and correctness risk.

Recommended fix:

- Validate `days` as integer, minimum 1, maximum 365 or a product-defined cap.
- Consider indexed materialized summaries if analytics grows beyond MVP volume.
- Add tests for low, high, negative, and non-numeric values.

Priority:

Fix with the next analytics iteration.

### F-006 - GitHub Client Does Not Fail Closed For Non-403/404 HTTP Errors

Severity: Medium

Evidence:

- `backend/app/Services/GitHub/HttpGitHubClient.php:45`

Impact:

The client maps 403 to rate limit and 404 to repository not found, but does not explicitly fail on 401, 409, 422, 5xx, malformed JSON, or abuse-rate responses. Some failures can be interpreted as empty trees or unsupported content later in the pipeline, producing misleading partial/success outcomes.

Recommended fix:

- Use `throw()` or explicit status handling after mapping known domain errors.
- Distinguish rate limit, auth failure, branch/ref not found, abuse detection, and transient GitHub errors.
- Persist structured failure codes in `IngestionRun.log`.

Priority:

Fix before relying on GitHub ingestion operationally.

### F-007 - Duplicate Ingestion Jobs Can Be Enqueued For The Same Plugin Version

Severity: Medium

Status: Mitigated in `fix/ingestion-run-controls`.

Evidence:

- `backend/app/Http/Controllers/Api/V1/PluginSyncController.php:25`
- `backend/app/Http/Controllers/Api/V1/GitHubWebhookController.php:50`
- `backend/app/Console/Commands/SyncScheduledPlugins.php:31`
- Scheduler uses `withoutOverlapping()` at command level in `backend/routes/console.php:11`, but manual sync and webhook paths can still overlap.

Impact:

Manual sync, scheduled sync, and webhooks can enqueue overlapping ingestion for the same plugin version. Natural-key upserts reduce data corruption risk, but overlapping jobs still waste GitHub/API/Search resources and can create racey run histories or stale index timing.

Recommended fix:

- Add a per-plugin-version queue uniqueness lock or Laravel `ShouldBeUnique`.
- Prevent enqueue when a queued/running run already exists unless the user explicitly retries.
- Record the enqueue source: manual, webhook, scheduled.

Priority:

Fix with ingestion observability/retry work.

### F-008 - Security Headers Lack Content Security Policy

Severity: Medium

Evidence:

- `backend/app/Http/Middleware/SecurityHeaders.php` sets basic hardening headers but no CSP.
- `frontend/src/server.ts` sets equivalent basic headers but no CSP.

Impact:

The app handles external Markdown-derived content and uses several controlled `innerHTML` surfaces. Angular sanitization helps, but CSP is a strong second layer against XSS and script injection. Absence of CSP weakens public hardening posture.

Recommended fix:

- Add a CSP suitable for Angular SSR, API, Reverb, fonts, images, and websocket connections.
- Avoid `unsafe-inline` if feasible; if Angular/runtime requires it temporarily, document why and phase it out.
- Add a smoke check for key pages with CSP enabled.

Priority:

Fix before public deployment.

### F-009 - Server-Side Sanitization Boundary Is Incomplete

Severity: Medium

Status: Mitigated in `fix/server-side-html-sanitization`.

Evidence:

- Backend stores and returns `content_html` through `DocumentResource`.
- Frontend sanitizes in `MarkdownRendererService`, and tests cover script removal.

Impact:

Current Angular screens are guarded, but the API exposes rendered HTML as stored by the backend. Any future client, integration, export, or admin view that trusts `content_html` directly can reintroduce XSS. Security depends on every consumer remembering to sanitize.

Recommended fix:

- Sanitize HTML server-side before persistence or before API response.
- Keep frontend sanitization as defense in depth.
- Add backend tests for malicious Markdown/HTML payloads.

Priority:

Fix when touching parser/ingestion next.

### F-010 - Production SSR Host Handling Is Brittle

Severity: Medium

Status: Mitigated in `fix/runtime-production-config`.

Evidence:

- `frontend/src/server.ts:15` allows only `localhost` and `127.0.0.1`.
- `frontend/src/server.ts:53` and `frontend/src/server.ts:57` build render/SEO URLs from `headers.host`.

Impact:

The current allow-list can break SSR behind a real production hostname. If loosened later without care, host header trust can poison canonical URLs and JSON-LD. This is both an availability and SEO/security-hardening concern.

Recommended fix:

- Add explicit `COMMANDSPHERE_ALLOWED_HOSTS` and `COMMANDSPHERE_PUBLIC_ORIGIN`.
- Use the configured public origin for canonical/JSON-LD generation instead of raw `Host` when in production.
- Add tests/smoke for allowed and rejected hosts.

Priority:

Fix alongside production deployment runbook.

### F-011 - Telemetry Is Operationally Thin

Severity: Medium

Evidence:

- Ingestion logs are persisted as JSON arrays in `IngestionRun.log`.
- Laravel logging remains default channel configuration.
- No request IDs, structured domain audit events, queue failure reporting, external metrics, or correlation IDs are documented.

Impact:

The product can show ingestion status, but diagnosing production incidents would still require ad hoc log digging. There is no clear way to correlate webhook request -> ingestion run -> queue job -> GitHub call -> Meilisearch update -> realtime broadcast.

Recommended fix:

- Introduce correlation IDs for ingestion runs and request logs.
- Add structured domain events for plugin creation, sync enqueue, run started, run completed, run failed, webhook rejected, and index updated.
- Document metrics: queue latency, run duration, documents parsed, warnings, GitHub statuses, search latency, broadcast failures.

Priority:

High-value portfolio improvement after security/config fixes.

### F-012 - Frontend Reverb Runtime Config Reads From LocalStorage

Severity: Low-Medium

Status: Mitigated in `fix/runtime-production-config`.

Evidence:

- `frontend/src/app/core/realtime/realtime.service.ts:95`

Impact:

This does not store secrets and is not a token persistence issue. However, using `localStorage` as an implicit runtime config channel is surprising and can make production behavior hard to reason about. If XSS ever exists, it could redirect websocket connection attempts.

Recommended fix:

- Replace localStorage-based runtime config with the same explicit runtime config used for public API origin.
- Keep environment overrides developer-only if needed.

Priority:

Fix with F-001.

### F-013 - Public Endpoint Auth Resolution Is Duplicated

Severity: Low-Medium

Evidence:

- `backend/app/Http/Controllers/Api/V1/CatalogController.php:214`
- `backend/app/Http/Controllers/Api/V1/SearchController.php:112`

Impact:

The duplicated `currentUser()` logic is currently careful: invalid authenticated-looking requests are not widened to anonymous scope. Duplication still increases the chance of future drift between discovery controllers.

Recommended fix:

- Extract to a dedicated service or request helper, for example `OptionalBearerUserResolver`.
- Add tests that invalid bearer tokens stay fail-closed or empty-scoped consistently across catalog and search.

Priority:

Refactor opportunistically.

### F-014 - Audit Tooling Finds High Vulnerabilities In Frontend Toolchain

Severity: Low-Medium for runtime, Medium for developer/CI environment

Evidence:

- `npm.cmd audit --audit-level=critical` returned 12 vulnerabilities: 8 high and 4 moderate, mainly Angular CLI/build tooling transitive dependencies (`esbuild`, `serialize-javascript`, `tar`, `uuid`).
- No critical vulnerabilities were reported.

Impact:

These appear mostly in build/dev tooling rather than shipped browser runtime, but they still affect CI/developer machines and should be tracked. The suggested forced fix is breaking, so it should be handled through Angular/toolchain upgrade planning, not blind `npm audit fix --force`.

Recommended fix:

- Keep critical audit as release gate.
- Track high build-tool advisories in the Brain and README risk section.
- Plan a controlled Angular CLI/build dependency upgrade.

Priority:

Monitor now; upgrade when compatible.

## Positive Controls Observed

- Dev-login is disabled outside `local` and `testing`.
- HMAC validation is present for GitHub webhooks.
- Search, sync, and webhook rate limiters exist.
- Private Reverb channels require community membership and scoped permissions.
- Discovery search tests prove community isolation for normal bearer-token paths.
- Markdown rendering sanitization is covered by frontend Jest.
- Search result highlighting escapes HTML before trusted insertion.
- Command code block highlighting escapes HTML before trusted insertion.
- Composer advisories are clean as of this audit.

## Telemetry Improvement Model

Recommended event taxonomy:

- `auth.discord.redirect_started`
- `auth.discord.callback_succeeded`
- `auth.discord.callback_failed`
- `plugin.created`
- `ingestion.enqueued`
- `ingestion.started`
- `ingestion.document.parsed`
- `ingestion.document.warning`
- `ingestion.failed`
- `ingestion.completed`
- `search.executed`
- `favorite.changed`
- `analytics.command_view.recorded`
- `webhook.github.rejected`
- `webhook.github.accepted`
- `realtime.broadcast.failed`

Minimum fields:

- `correlation_id`
- `user_id` when authenticated
- `community_id`
- `plugin_id`
- `plugin_version_id`
- `ingestion_run_id`
- `source` (`manual`, `webhook`, `scheduled`)
- duration and status code where relevant

## Recommended Fix Order

1. Fix production runtime configuration: browser API origin, Reverb origin, allowed hosts, public origin.
2. Add OAuth `state` protection.
3. Make CI service-backed and add audit gates.
4. Add ingestion job uniqueness and run pagination/scoping.
5. Bound analytics query windows and add tests.
6. Harden GitHub client error taxonomy.
7. Add CSP and server-side HTML sanitization.
8. Add structured telemetry/correlation IDs.

## Validation Performed

Validated:

- `composer audit` passed with no advisories.
- `cd frontend; npm.cmd run lint` passed.
- `cd frontend; npm.cmd test -- --runInBand` passed: 9 suites, 11 tests.
- `cd frontend; npm.cmd run build` passed with warnings.
- `cd backend; .\vendor\bin\pint.bat --test` passed.
- `npm.cmd audit --audit-level=critical` completed and reported no critical vulnerabilities, but did report high/moderate vulnerabilities.

Pending:

- Full backend Pest suite: local run timed out after 180 seconds.
- Docker Compose integration smoke.
- Playwright E2E.
- Runtime browser validation against production Compose.

Build warnings to track:

- Angular build reports CommonJS optimization bailouts for `compression` and `pusher-js`.
- Angular build reports repeated skipped CSS selector rules with `& -> Empty sub-selector`.

## Audit Conclusion

The product is a strong portfolio v1, but it should not be described as production-ready without hardening the deployment configuration, OAuth flow, CI integration services, bounded operational queries, and telemetry. The next engineering phase should be a security/production-readiness hardening branch rather than another product feature.
