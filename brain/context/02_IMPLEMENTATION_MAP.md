# Implementation Map

## Repository Layout

```text
backend/   Laravel API, domain models, ingestion jobs, policies, Reverb, Horizon
frontend/  Angular SSR app, UI system, public pages, admin pages, E2E tests
docker/    Development and production Dockerfiles plus Nginx config
docs/      Product vision, ADRs, progress, human actions, QA screenshots
brain/     Operational memory for future implementation loops
```

## Backend Entry Points

API routes live in `backend/routes/api.php`.

Public or mixed public/authenticated reads:

- `GET /api/v1/search`
- `GET /api/v1/communities`
- `GET /api/v1/communities/{community:slug}`
- `GET /api/v1/plugins`
- `GET /api/v1/plugins/{slug}`
- `GET /api/v1/plugins/{slug}/versions/{version}/documents`
- `GET /api/v1/documents/{document}`
- `GET /api/v1/commands/{slug}`

Auth/session:

- `GET /api/v1/auth/discord/redirect`
- `GET /api/v1/auth/discord/callback`
- `POST /api/v1/auth/dev-login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Operations and mutations:

- `POST /api/v1/plugins`
- `POST /api/v1/plugins/{plugin}/sync`
- `GET /api/v1/ingestions`
- `GET /api/v1/ingestions/{ingestionRun}`
- `POST /api/v1/favorites`
- `DELETE /api/v1/favorites`
- `POST /api/v1/commands/{slug}/view`
- `GET /api/v1/analytics/most-viewed`
- `POST /api/v1/webhooks/github`

## Backend Services

Ingestion:

- `App\Services\Ingestion\IngestionService`
  - creates/updates `IngestionRun`;
  - fetches Markdown through `GitHubClient`;
  - parses documents through `MarkdownParser`;
  - persists documents and commands transactionally per document;
  - reconciles stale commands;
  - updates Scout index;
  - invalidates discovery cache;
  - dispatches realtime events.

Markdown:

- `App\Services\Markdown\MarkdownParser`
  - extracts frontmatter;
  - converts Markdown to HTML through CommonMark;
  - extracts commands from frontmatter `commands:` and `## /command` sections;
  - normalizes aliases, parameters, slugs, categories;
  - emits warnings for malformed or unsupported command declarations.

Discovery:

- `App\Services\Discovery\DiscoveryAccess`
  - centralizes community-scoped query builders.
- `App\Services\Discovery\DiscoveryCache`
  - uses versioned Redis keys for public/user discovery read cache.

GitHub:

- `App\Contracts\GitHubClient`
- `App\Services\GitHub\HttpGitHubClient`
- `App\Services\GitHub\FixtureGitHubClient`

Auth and authorization:

- Sanctum bearer tokens.
- Discord Socialite stateless OAuth2.
- Dev-login only in local/testing.
- Policies and middleware enforce plugin/community permissions.

## Frontend Routes

Routes live in `frontend/src/app/app.routes.ts`.

Public:

- `/`
- `/login`
- `/c/:community`
- `/c/:community/p/:plugin`
- `/c/:community/p/:plugin/commands/:slug`
- `/commands/:slug`
- `/search`

Authenticated:

- `/favorites`
- `/analytics`
- `/admin/plugins`
- `/admin/ingestions`

## Frontend Core Services

- `AuthService`
  - in-memory token;
  - user/community signals;
  - dev-login, Discord redirect, logout, `me`.
- `SearchService`
  - command search API integration.
- `SearchUrlStateService`
  - URL-backed query/facet state.
- `MarkdownRendererService`
  - sanitizes HTML and enriches headings/code blocks.
- `RealtimeService`
  - SSR-safe Laravel Echo/Reverb private channel subscription.
- `SeoService`
  - title, meta, canonical, Open Graph, JSON-LD.
- `ShellDataService`
  - shell-level data loading.
- `FavoriteService`, `CatalogService`, `IngestionService`, `AnalyticsService`
  - domain-specific API clients.

## Tests And Fixtures

Backend tests:

- `backend/tests/Feature/IngestionPipelineTest.php`
- `backend/tests/Feature/DiscoveryApiTest.php`
- `backend/tests/Feature/CommunityPermissionScopeTest.php`
- `backend/tests/Feature/AutomationRealtimeTest.php`
- `backend/tests/Feature/AuthApiTest.php`

Frontend tests:

- Component/service specs under `frontend/src/app/**/*.spec.ts`.
- Playwright happy paths under `frontend/e2e/portfolio-happy-paths.spec.ts`.

Fixtures:

- Markdown ingestion fixtures under `backend/tests/fixtures/ingestion`.

## Extension Notes

- Add new backend contracts through resources and tests before wiring UI.
- Keep public API responses stable through API Resources.
- Keep large domain workflows out of controllers.
- Keep SSR pages free of unguarded browser-only APIs.
- Add i18n keys for user-facing text.
- Prefer feature-specific services over a single catch-all frontend store.
- When adding search facets, update Meilisearch index settings, API filter construction, URL state, UI, and tests together.
