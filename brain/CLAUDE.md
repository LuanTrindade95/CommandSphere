# CommandSphere Brain

This Brain is the operational memory for CommandSphere. Use it before planning or implementing new improvements.

CommandSphere is a personal senior portfolio project, not a Celem plugin repository. Treat every change as production-grade full stack SaaS work: architecture, UX, security, performance, documentation, and repository hygiene all matter.

## Required Reading Order

Read these files before planning implementation work:

1. `brain/canonico/CURRENT_STATE.md`
2. `brain/canonico/NEXT_ACTIONS.md`
3. `brain/canonico/DECISIONS.md`
4. `brain/context/00_INDEX.md`
5. Any context file referenced by `00_INDEX.md` for the target area.
6. Existing repository docs: `README.md`, `docs/VISION.md`, `docs/DECISIONS.md`, `docs/PROGRESS.md`, `docs/HUMAN-ACTIONS.md`.

If the Brain and source code disagree, source code and tests are factual truth. Update the Brain after confirming the real implementation.

## Project Context

CommandSphere is a full-stack documentation discovery platform for plugin ecosystems. It ingests Markdown from GitHub, parses documents, extracts command metadata, indexes commands in Meilisearch, and serves an Angular SSR developer-tool UX with auth, permissions, favorites, analytics, realtime ingestion status, SEO, and Docker packaging.

Current stack:

- Backend: Laravel 12, PHP 8.3, Sanctum, Socialite Discord OAuth2, Spatie permissions, Scout, Meilisearch, Horizon, Reverb, MySQL 8, Redis.
- Frontend: Angular 19 standalone, SSR/hydration, Signals, RxJS, Transloco, Tailwind CSS 4, lucide-angular, Jest, Playwright.
- Infra: Docker Compose for dev and prod-like local validation.

## Operating Rules

- Always inspect the branch and worktree before editing.
- Never work directly on `main`.
- Use a dedicated task branch, normally `docs/...`, `feature/...`, `fix/...`, or `refactor/...`.
- Do not mix product code, documentation cleanup, and unrelated refactors.
- Preserve existing architecture before introducing new abstractions.
- Document structural or architectural changes in `docs/DECISIONS.md` and update the Brain.
- Keep portfolio quality visible: improvements should demonstrate business realism, senior engineering, and polished UX.

## CommandSphere Gate Set

Choose the subset that matches the touched surface, but call out anything not run as `PENDING`.

Backend gates:

```bash
docker compose exec -T backend ./vendor/bin/pest
docker compose exec -T backend ./vendor/bin/pint --test
docker compose exec -T backend composer audit
```

Pest wipes the development database: Feature tests use `RefreshDatabase` and the suite runs against `commandsphere`, because `backend/phpunit.xml` keeps its database overrides commented out and there is no `backend/.env.testing`. Run any gate that needs data, E2E above all, **before** Pest, and reseed with the runtime gates afterwards. CI is unaffected; it sets `DB_DATABASE=commandsphere_test`.

Frontend gates:

```bash
cd frontend
npx tsc --noEmit
npm run lint
npm test -- --runInBand
npm run build
npm run e2e
npm audit --audit-level=critical
```

Runtime and integration gates when the change touches ingestion, search, realtime, SSR, Docker, or infra:

```bash
docker compose up --build
docker compose exec -T backend php artisan migrate:fresh --seed
docker compose exec -T backend php artisan scout:sync-index-settings
docker compose exec -T backend php artisan scout:import "App\Models\Command"
docker compose -f docker-compose.prod.yml up -d --build
```

For documentation-only changes, validate by checking links, factual alignment with the source tree, and `git diff`.

## Build-Loop Compatibility

When using a build-loop workflow, this file is the Brain entrypoint. The orchestrator may read and update `brain/` during planning and closing. Product implementation phases must not casually edit `brain/`; update it deliberately after the change is validated.

## Closing Checklist

Before finishing any meaningful task:

- Confirm changed files are scoped.
- Run relevant gates or explicitly mark them `PENDING`.
- Update `brain/canonico/CURRENT_STATE.md` when project state changes.
- Update `brain/canonico/NEXT_ACTIONS.md` when roadmap or priorities change.
- Update `brain/canonico/DECISIONS.md` and `docs/DECISIONS.md` when architecture decisions change.
- Leave a concise handoff in `brain/handoffs/` for multi-step work.
