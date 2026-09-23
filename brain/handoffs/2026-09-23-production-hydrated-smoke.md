# Handoff - Production Hydrated-Browser Smoke

Date: 2026-09-23
Branch: `claude/estruturar-agentes-20566a` (validation only, no product change)

## Scope

Item C7: close the last open acceptance criterion of Priority 2, "Docker production smoke validates hydrated browser API calls". Nothing in the product was allowed to change; the item only had to prove that the hydrated browser uses the configured public origin instead of the `localhost` defaults.

## How It Was Validated

The stack ran twice, with two disjoint sets of public values, in throwaway Compose projects with their own volumes.

- First pass: `127.0.0.1:4200` as public origin, `127.0.0.1:8000/api/v1` as public API, Reverb on `127.0.0.1:8080`.
- Adversarial pass: `127.0.0.55:4373` as public origin, `127.0.0.55:8373/api/v1` as public API, Reverb on `127.0.0.55:8473`, with `COMMANDSPHERE_ALLOWED_HOSTS` matching. Ports were bound to `127.0.0.55` only, so the default origin answered connection refused and any leak would fail loudly instead of silently succeeding.

Secrets were generated per run into an env file outside the repository. No versioned file was touched in either pass; `git diff main` stayed empty.

## Proven

- Canonical, `og:url`, `og:image`, `twitter:image`, and JSON-LD carry the configured public origin on the landing page, a plugin page, and a command page. Zero occurrences of the default origin.
- `/runtime-config.js` serves `apiBaseUrl`, `publicOrigin`, and the Reverb block exactly as configured, with `Cache-Control: no-store`, and the served Reverb app key matches the configured one.
- After hydration, with hydration proven by a typed search that produced DOM results and a `routerLink` navigation that issued no new document request, 53 requests were captured and every one landed on a configured origin. API calls covered search, command detail, plugin detail, and plugin version documents.
- The browser websocket opens `ws://<configured host>:<configured port>/app/<key>` and Reverb answers `pusher:connection_established`. The public variable wins over the `COMMANDSPHERE_REVERB_HOST: localhost` pinned in the `frontend` service of `docker-compose.prod.yml`.
- A forged `Host` header (`evil.example.com`, `attacker.test`) never reaches canonical or metadata, because `publicUrlFor()` in `frontend/src/server.ts` builds URLs from `COMMANDSPHERE_PUBLIC_ORIGIN` alone.
- `POST /api/v1/auth/dev-login` answers `403 auth.dev_login_disabled` in production, and refuses before request validation.

## Remaining Work

- Seeding is broken in the production image: `fakerphp/faker` is a dev dependency and the image installs `--no-dev`. Tracked as Priority 13. The adversarial pass worked around it with direct SQL inserts into the throwaway volume, which is how the plugin and command pages could be tested at all.
- No Content Security Policy exists anywhere in the stack. Tracked in the backlog; the clean console in this smoke says nothing about policy.
- The API serves `Access-Control-Allow-Origin: *`. Tracked in the backlog.

## Gates

- Production Docker runtime gate: `VALIDATED` in both passes, including migrations and Scout index sync.
- `docker compose ... migrate:fresh --seed`: `FAILED` for the reason above, not by regression.
- Backend and frontend unit, lint, and build gates: `PENDING`. This item changed no code, so they were not run.
