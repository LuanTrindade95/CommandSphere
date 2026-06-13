# Current State

Updated: 2026-06-13

## Product Status

CommandSphere v1 is implemented as a complete portfolio-grade full stack product. The repository already contains backend domain modeling, ingestion, search, permissions, frontend SSR screens, realtime operations, SEO, hardening, tests, screenshots, and Docker packaging.

The product is positioned as a documentation discovery platform for plugin ecosystems. It turns GitHub Markdown documentation into searchable, structured, versioned plugin and command knowledge.

## Implemented Capabilities

- Public SSR landing, search, community catalog, plugin documentation, document viewer, and command pages.
- Discord OAuth2 and local/testing dev-login through Laravel Sanctum bearer tokens.
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
