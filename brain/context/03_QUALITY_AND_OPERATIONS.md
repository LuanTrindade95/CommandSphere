# Quality And Operations

## Validation Strategy

CommandSphere quality is split by surface area.

Backend:

- Pest feature/unit tests.
- Pint formatting check.
- Composer audit.
- Docker smoke when service integration matters.

Frontend:

- TypeScript strict checks through `npx tsc --noEmit`.
- `npm run lint` currently maps to TypeScript no-emit.
- Jest unit/component tests.
- Angular SSR build.
- Playwright E2E for portfolio flows.
- npm critical vulnerability audit.

Runtime:

- Docker Compose validates MySQL, Redis, Meilisearch, backend, Horizon, Reverb, and frontend together.
- Production Compose validates multi-stage packaging and process topology.

## Gate Selection

Documentation-only:

- Inspect changed docs.
- Check links and factual references.
- Review `git diff`.

Backend-only:

- Run Pest and Pint.
- Add composer audit for dependency or security changes.
- Add Docker smoke for database, queue, cache, search, webhook, or realtime changes.

Frontend-only:

- Run TypeScript, Jest, build.
- Run Playwright for user-visible flows, routing, auth, search, SSR, or layout changes.
- Use browser screenshots for meaningful UI changes.

Cross-stack:

- Run backend and frontend gates.
- Run Docker Compose with migrations, seed, Meilisearch settings sync, and Scout import.

## Operational Invariants

- No secrets in source.
- `.env.example` documents required variables.
- Discord OAuth credentials are human/operator supplied.
- GitHub webhook secret must be configured on both sides.
- Meilisearch settings/import must be explicitly synchronized after setup.
- Horizon processes queued ingestion.
- Reverb uses private community channels and Sanctum-authenticated broadcast auth.
- Public discovery must remain indexable but not operationally sensitive.

## Security Watchpoints

- Markdown content must remain sanitized before `SafeHtml`.
- Prefer server-side sanitization before storing or returning `content_html`; keep frontend sanitization as defense in depth.
- Webhook signature validation must fail closed when secret is missing.
- Discord OAuth must use state/CSRF protection before public production use.
- Dev-login must stay disabled outside local/testing.
- Invalid bearer tokens must not widen request scope to anonymous public data.
- Search filters must always include accessible community scope.
- Reverb channel authorization must remain community-scoped.
- Rate limits should protect search, sync, and webhook surfaces.
- Content Security Policy is currently a hardening gap and should be added before public deployment.

## Performance Watchpoints

- Avoid N+1 in catalog/search/plugin/document/command reads.
- Avoid loading all tenant-scoped operational history before permission filtering; scope and paginate in SQL.
- Keep Meilisearch payloads lean.
- Preserve ETag-based GitHub fetch reduction.
- Avoid reindexing during intermediate ingestion states.
- Cache discovery read models by versioned keys and invalidate after ingestion changes.
- Keep SSR data loading bounded and request-safe.
- Bound analytics windows and other user-controlled query ranges.

## Audit Baseline

The broad audit captured on 2026-06-13 is stored at `brain/audits/2026-06-13-system-audit.md`.

Before claiming production readiness, address or explicitly defer:

- browser API/Reverb runtime config;
- OAuth state protection;
- CI services and audit gates;
- ingestion uniqueness, query scoping, and pagination;
- analytics period bounds;
- GitHub HTTP error taxonomy;
- CSP and server-side HTML sanitization;
- telemetry/correlation IDs.

## Documentation Expectations

Update docs when behavior changes:

- `README.md` for setup, architecture, stack, quality, status, or user-facing product changes.
- `docs/DECISIONS.md` for architecture decisions and trade-offs.
- `docs/HUMAN-ACTIONS.md` for credentials, deploy steps, external configuration, or manual validation.
- `docs/PROGRESS.md` for phase-level completion evidence.
- `brain/canonico/*` for current state, next actions, and operational memory.

## Release Readiness Heuristic

A change is not portfolio-ready until:

- UX has polished loading, empty, error, and permission states where relevant.
- Backend behavior is covered at the permission and business-rule level.
- Frontend state is tested if it coordinates URLs, auth, realtime, or rendering.
- SSR compatibility is considered.
- Operational or deployment implications are documented.
- Residual risks are named honestly.
