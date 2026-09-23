# Handoff - Production Smoke Re-Run With CSP Enforced

Date: 2026-09-23
Branch: `docs/c7-smoke-recheck` (validation only, no product change)
Stack built from: `c4eee46`

## Scope

The first production hydrated-browser smoke ran on `29a7881`. Content-Security-Policy (ADR-30), the optional bearer resolver (ADR-31), and correlation-ID telemetry (ADR-29) landed afterwards and changed `frontend/src/server.ts` and the backend middleware stack, so the proof no longer covered the shipped code. This round re-ran the smoke on current `main` and added CSP to what has to be proven.

## How It Was Validated

Two independent passes, each with its own public values, in throwaway Compose projects with their own volumes. Ports were published on the chosen IP only, so the default origin answered connection refused.

- Execution pass: public origin `127.0.0.212:4614`, API `:8514`, Reverb `:8714`.
- Audit pass: public origin `127.0.0.77:4677`, API `:8077`, Reverb `:8177`, built independently without the execution pass's method.

Port remapping used a Compose overlay in the scratchpad with the `!override` tag, because the default Compose merge appends to `ports` and would leave the defaults bound on `0.0.0.0`. No versioned file was touched; `git status` and `git diff HEAD` stayed empty.

## Proven

- Canonical, `og:url`, `og:image`, and JSON-LD carry the configured public origin on the landing, plugin, and command pages, with zero occurrences of a default origin. Prerendered routes go through the same renderer and carry CSP, nonce, and `Cache-Control: no-store`.
- `/runtime-config.js` serves the configured API origin, public origin, and Reverb block.
- After a proven hydration barrier, every captured request landed on a configured origin. Hydration was proven by a typed search that produced a result in the DOM and a `routerLink` navigation that issued no new document request.
- CSP is derived from `resolveRuntimeBrowserConfig()`, the same source as `/runtime-config.js`: `connect-src` carries the configured API and Reverb origins and no defaults. The `style-src` nonce changes on every response to the same URL.
- CSP is enforced rather than announced. Probes on a served page recorded `disposition: enforce` for an inline style without nonce, an inline script, a foreign script, and a foreign `fetch`, while a style carrying the header nonce applied normally.
- The browser websocket reaches the configured Reverb host under CSP and receives `pusher:connection_established`, with no violation.
- A forged `Host`, and a forged `X-Forwarded-Host` with `X-Forwarded-Proto: https`, never reach canonical or metadata.
- `POST /api/v1/auth/dev-login` answers `403 auth.dev_login_disabled` before request validation. The API sends `default-src 'none'` on success and error responses alike.

## Correction To The Record

The execution pass hit `500` with `Attribute community is not filterable` and attributed it to direct SQL inserts bypassing the Scout observers. The audit disproved that mechanism: building the dataset entirely through Eloquent, with observers and Horizon active, still produced an index with empty `filterableAttributes` and the same `500`.

The real fact is that no code path applies index settings. Only `php artisan scout:sync-index-settings` does, and it is the documented step in `README.md`. After it runs once, the normal path stays consistent, including a command created afterwards and searched with a community facet. This is an operational step, not a regression, and it is now recorded in `CURRENT_STATE.md` and as a backlog decision.

## Remaining Work

- Seeding stays broken in the production image, Priority 13, symptom reconfirmed on `c4eee46`.
- `Access-Control-Allow-Origin: *` confirmed on every API response, including errors and a forged `Origin`.
- API responses carry duplicated security headers from nginx and the `SecurityHeaders` middleware.
- Whether index settings should be applied by code, or the missing-settings failure surfaced explicitly instead of a generic `500`.

## Gates

- Production Docker runtime gate: `VALIDATED` in both passes, including migrations and Scout index sync.
- `db:seed` in the production image: `FAILED`, for the known Priority 13 reason.
- Backend and frontend unit, lint, and build gates: `PENDING`. This item changed no code, so they were not run.
