# Current State

Updated: 2026-06-13

## Product Status

CommandSphere v1 is implemented as a complete portfolio-grade full stack product. The repository already contains backend domain modeling, ingestion, search, permissions, frontend SSR screens, realtime operations, SEO, hardening, tests, screenshots, and Docker packaging.

The product is positioned as a documentation discovery platform for plugin ecosystems. It turns GitHub Markdown documentation into searchable, structured, versioned plugin and command knowledge.

## Implemented Capabilities

- Public SSR landing, search, community catalog, plugin documentation, document viewer, and command pages.
- Discord OAuth2 with signed short-lived state cookie validation, plus local/testing dev-login through Laravel Sanctum bearer tokens.
- Community-scoped permissions using Spatie teams with `community_id`.
- Plugin and plugin version domain model with documents, commands, categories, favorites, views, and ingestion runs.
- GitHub Markdown ingestion with ETag support, idempotent upserts, command extraction, stale command reconciliation, warnings, and partial run handling.
- Search through Laravel Scout and Meilisearch with facets for community, plugin, and category.
- Redis-backed discovery cache invalidated by a global version key after ingestion.
- Favorites and command-view analytics with short-window dedupe.
- GitHub webhook validation through HMAC SHA-256.
- Scheduled sync command for latest plugin versions.
- Reverb private channels by community for ingestion status and command index updates.
- Angular SSR shell with lazy standalone routes, Signals-based UI state, Transloco i18n, command palette, protected routes, and reusable UI components.
- Browser/SSR runtime configuration separates internal SSR API calls from public browser API, Reverb, allowed hosts, and canonical public origin.
- Markdown viewer sanitization and heading/code enhancement.
- Server-side HTML sanitization of `content_html` in two layers: allowlist sanitizer applied at ingestion and again through a `Document` accessor on every read, so stored documents are served sanitized without rewriting the column.
- SEO metadata, canonical URLs, Open Graph, JSON-LD, robots, sitemap, and portfolio screenshots, with SSR post-processing normalizing public origin metadata.
- Development and production-like Docker Compose stacks.

## Source Of Truth Files

- Product overview and setup: `README.md`
- Product vision and domain scope: `docs/VISION.md`
- Architecture decisions: `docs/DECISIONS.md`
- Historical phase progress: `docs/PROGRESS.md`
- Manual environment actions: `docs/HUMAN-ACTIONS.md`
- Backend API routes: `backend/routes/api.php`
- Frontend routes: `frontend/src/app/app.routes.ts`
- Docker topology: `docker-compose.yml`, `docker-compose.prod.yml`

## Backend Architecture Snapshot

The backend is a Laravel 12 API under `backend/`.

Core patterns:

- Controllers stay thin and delegate domain rules to services, policies, resources, jobs, and models.
- API is versioned under `/api/v1`.
- Public discovery endpoints are readable anonymously, but authenticated-looking requests with invalid tokens are not widened to public scope.
- Mutating and operational endpoints require Sanctum auth and permission checks.
- Ingestion is asynchronous-capable through jobs and Horizon, but can be exercised through services in tests.
- Natural keys protect ingestion idempotency:
  - `plugins(community_id, slug)`
  - `documents(plugin_version_id, path)`
  - `commands(plugin_version_id, slug)`

Important implementation anchors:

- `App\Services\Ingestion\IngestionService`
- `App\Services\Markdown\MarkdownParser`
- `App\Services\Markdown\HtmlSanitizer`
- `App\Services\Discovery\DiscoveryAccess`
- `App\Services\Discovery\DiscoveryCache`
- `App\Contracts\GitHubClient`
- `App\Services\GitHub\HttpGitHubClient`
- `App\Jobs\RunPluginVersionIngestion`
- `App\Events\IngestionRunStatusChanged`
- `App\Events\CommandIndexUpdated`

## Frontend Architecture Snapshot

The frontend is an Angular 19 SSR app under `frontend/`.

Core patterns:

- Standalone lazy routes.
- Signals for local UI state and auth session state.
- RxJS for HTTP flows and async orchestration.
- Reusable UI primitives live under `frontend/src/app/shared/ui`.
- Domain data clients live under `frontend/src/app/core`.
- Public pages are SSR-compatible and should avoid browser-only APIs unless guarded with `isPlatformBrowser`.
- Auth token is intentionally stored in memory only.
- User-facing strings should go through Transloco dictionaries.

Important implementation anchors:

- `frontend/src/app/app.routes.ts`
- `frontend/src/app/shell/app-shell.component.ts`
- `frontend/src/app/core/auth/auth.service.ts`
- `frontend/src/app/core/search/search.service.ts`
- `frontend/src/app/core/search/search-url-state.service.ts`
- `frontend/src/app/core/markdown/markdown-renderer.service.ts`
- `frontend/src/app/core/realtime/realtime.service.ts`
- `frontend/src/app/core/seo/seo.service.ts`

## Quality Snapshot

Documented evidence in `README.md` and `docs/PROGRESS.md` says v1 has passed phase validations across Pest, Pint, TypeScript, Jest, SSR build, Playwright, Docker, Meilisearch, Reverb, Horizon, and production-like Docker smoke checks.

Current Brain bootstrap did not rerun the full product gate set because this change only adds documentation. Future product changes must run relevant gates and record `VALIDATED` versus `PENDING`.

## Known Constraints

- Real Discord OAuth requires operator-provided credentials.
- Real GitHub webhook use requires matching `GITHUB_WEBHOOK_SECRET` in environment and GitHub.
- Meilisearch settings/import must be synchronized after migrations/seeding.
- Reverb/Horizon validation needs the Docker runtime, not just unit tests.
- Token persistence is intentionally limited to memory; refresh loses session until a future session strategy is chosen.
- Public discovery must remain clearly separated from private operational data.
- Search correctness depends on keeping Scout payload fields and Meilisearch filter settings aligned.

## Current Branch Context

This Brain was bootstrapped on branch `docs/commandsphere-brain-documentation`, created from `feature/commandsphere-portfolio-v1-polish`.

Active remediation branch: `fix/runtime-production-config`.

Latest build-loop phase completed in this branch:

- F-001/F-010/F-012 runtime public configuration hardening.
- Added `frontend/public/runtime-config.js` and SSR `/runtime-config.js` endpoint.
- Moved Angular API/Reverb/SEO consumers to `COMMANDSPHERE_RUNTIME_CONFIG`.
- Added public origin post-processing for canonical URL, Open Graph URL/image, Twitter image, and JSON-LD.
- Documented public SSR/frontend variables in `.env.example`, `README.md`, and Compose files.

Active follow-up remediation branch: `fix/oauth-state-hardening`.

Latest OAuth hardening phase:

- F-002 Discord OAuth state/CSRF hardening.
- Replaced unsupported generic `Socialite::driver('discord')` usage with an explicit first-party Discord Socialite provider.
- Added signed 10-minute httpOnly `commandsphere_discord_oauth_state` cookie on redirect.
- Callback now rejects missing, tampered, mismatched, or expired OAuth state before calling Discord.
- Callback clears the state cookie on both success and failure.

Active CI remediation branch: `fix/ci-service-gates`.

Latest CI hardening phase:

- F-003 CI/service-backed validation hardening.
- Backend CI now provisions MySQL, Redis, and Meilisearch service containers.
- Backend CI runs `composer audit`, Pint, and Pest with explicit testing environment variables.
- Frontend CI now runs `npm audit --audit-level=critical` before SSR build.
- Meilisearch readiness is checked with a bounded wait loop before backend dependency/test steps.

Active ingestion remediation branch: `fix/ingestion-run-controls`.

Latest ingestion hardening phase:

- F-004 ingestion run list scoping/pagination.
- F-007 duplicate ingestion enqueue prevention.
- Ingestion runs now record source: `manual`, `webhook`, or `scheduled`.
- `IngestionService::start()` reuses active `queued/running` runs unless forced.
- `RunPluginVersionIngestion` is unique per plugin version for one hour.
- Admin ingestion listing now scopes communities in SQL and returns pagination metadata.

Active analytics remediation branch: `fix/analytics-bounds`.

Latest analytics hardening phase:

- F-005 analytics period bounds.
- `/api/v1/analytics/most-viewed` now validates `days` as integer `1..COMMANDSPHERE_ANALYTICS_MAX_DAYS`.
- Analytics response metadata now includes `days` and `max_days`.
- Local tests isolate command-view dedupe from Meilisearch when search indexing is not the behavior under test.

Active sanitization remediation branch: `fix/server-side-html-sanitization`.

Latest sanitization hardening phase:

- F-009 server-side sanitization boundary (see ADR-26).
- Markdown conversion now runs with `html_input=escape` and `allow_unsafe_links=false`.
- `HtmlSanitizer` applies a tag/attribute allowlist over `DOMDocument`; `href`/`src` accept only `http`, `https`, `mailto`, and relative paths, with control bytes stripped before the scheme check.
- A `Document` accessor sanitizes `content_html` on every read, so rows stored before the fix are served sanitized without any data migration.
- Angular `MarkdownRendererService` is untouched and remains defense in depth.
- Gates: Pest `VALIDATED` (55 passed), Pint `VALIDATED` (118 files). `composer audit` reports 22 pre-existing advisories in 4 packages, and Jest has 2 pre-existing failures in `runtime-config.spec.ts`; both sets are identical to the `a3a8299` baseline and unrelated to this change.
