# Next Actions

Updated: 2026-06-13

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

Status: Implemented in branch `fix/runtime-production-config` as the first build-loop remediation phase. Keep the Docker production hydrated-browser smoke as a remaining validation item before treating this as fully production-proven.

Recommended direction:

- Introduce explicit public runtime configuration for browser API origin, Reverb host, Reverb scheme, Reverb port, allowed SSR hosts, and public canonical origin.
- Prefer a same-origin reverse proxy (`/api/v1`, websocket path) when possible.
- Stop deriving production canonical/JSON-LD URLs from raw request `Host`.

Acceptance criteria:

- Production-mode browser calls do not point to localhost. `VALIDATED` through SSR artifact smoke with public runtime config.
- SSR uses the configured production origin for canonical, Open Graph, and JSON-LD metadata. `VALIDATED` through SSR artifact smoke.
- Reverb uses documented production runtime config. `VALIDATED` at configuration level; websocket integration remains part of Docker/E2E validation.
- Docker production smoke validates hydrated browser API calls.

Risks:

- Misaligned backend/frontend origins can break OAuth callback, broadcast auth, and CORS if introduced without an integration test.

## Priority 3 - OAuth State And Auth Hardening

Problem: Discord OAuth currently uses `stateless()`, which removes OAuth `state` verification and weakens login CSRF/account-injection protection.

Recommended direction:

- Add signed state for Discord OAuth.
- Store state in short-lived httpOnly cookie or server-side cache.
- Bind state to a safe frontend return URL.
- Add invalid/missing/expired/reused state tests.

Acceptance criteria:

- OAuth callback rejects missing or invalid state.
- Valid Discord login still creates/updates the user and attaches default community membership.
- Auth flow remains SSR-safe and does not persist bearer tokens in browser storage.

Risks:

- Cookie settings must be coordinated with the session strategy and deployment origin.

## Priority 4 - CI And Service-Backed Test Hardening

Problem: CI currently does not define MySQL, Redis, or Meilisearch services, while important tests and product claims depend on those systems.

Recommended direction:

- Split fast unit tests from service-backed integration tests, or add required CI services.
- Add `composer audit`, `npm audit --audit-level=critical`, and Playwright where feasible.
- Ensure Meilisearch tests fail fast when the service is missing.

Acceptance criteria:

- CI proves backend tests, frontend tests, SSR build, dependency audits, and service-backed search behavior.
- CI logs clearly distinguish skipped, pending, and executed integration checks.

Risks:

- CI runtime will increase. Keep jobs parallel and cache dependencies.

## Priority 5 - Ingestion Operations Hardening

Problem: Manual sync, scheduled sync, and webhooks can enqueue overlapping runs for the same plugin version. Ingestion run listing also loads all runs before permission filtering.

Recommended direction:

- Add per-plugin-version uniqueness/locking for queued and running ingestion jobs.
- Add retry semantics that create new auditable runs.
- Scope ingestion run queries in SQL and paginate the admin endpoint.
- Record run source: manual, webhook, scheduled.

Acceptance criteria:

- Duplicate enqueue attempts do not create overlapping jobs unless explicitly forced.
- Admin ingestion list is paginated and scoped in the query.
- Realtime updates and historical run detail still work.

Risks:

- Existing run history should remain readable after adding source/lock metadata.

## Priority 6 - Analytics Bounds And Telemetry

Problem: Analytics period is unbounded and system telemetry is mostly implicit in `IngestionRun.log`.

Recommended direction:

- Validate analytics `days` with a product cap.
- Add structured operational events and correlation IDs.
- Track webhook -> run -> job -> GitHub -> Meilisearch -> broadcast flow.

Acceptance criteria:

- Analytics rejects invalid periods and caps expensive windows.
- Logs/events include correlation IDs and relevant domain IDs.
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

## Backlog

- Add Content Security Policy on Laravel and SSR Node responses.
- Sanitize Markdown HTML server-side before storage or response.
- Harden GitHub client HTTP error taxonomy beyond 403/404.
- Extract optional bearer-token user resolution shared by public discovery controllers.
- Track Angular toolchain advisories from `npm audit`.
- Keyboard-first power-user UX for command palette actions.
- Saved searches and team-level curated collections.
- Export command/document references.
- Accessibility audit and fixes with Playwright or axe integration.
- API pagination standardization for large communities.
- More granular audit events for admin actions.
- Advanced ranking using views, recency, plugin popularity, and exact syntax match.
- Visual regression baseline for portfolio screenshots.
