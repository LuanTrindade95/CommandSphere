# Handoff - Content Security Policy

Date: 2026-09-21
Branch: `fix/security-content-policy`

## Scope

Audit finding F-008: neither the Laravel API nor the SSR Node server sent `Content-Security-Policy`, leaving no browser-enforced layer above the sanitization of third-party Markdown (ADR-18, ADR-28).

## Implemented

- `frontend/src/server/content-security-policy.ts`: `buildContentSecurityPolicy`, a per-request nonce generator, and `resolveRuntimeBrowserConfig`, which also feeds `/runtime-config.js`. `connect-src` adds the Reverb WebSocket origin and adds the API origin only when it differs from the page origin.
- `frontend/src/server.ts`: every rendered HTML response carries the policy and `Cache-Control: no-store`; the nonce is stamped on `app-root` as `ngCspNonce`; `CommonEngine.render` runs with `inlineCriticalCss: false`. `/runtime-config.js` gets `default-src 'none'`.
- `frontend/src/server/prerendered-html.ts`: requests ending in `.html`, matched case-insensitively, skip `express.static` and are mapped back to their SPA route, so prerendered documents are rendered with the policy instead of served raw.
- `frontend/angular.json`: `inlineCritical: false` in the production build.
- `UiIconComponent` and `UiSkeletonComponent` use static Tailwind classes instead of `[style.*]`; the analytics bar uses an SVG `rect` with `[attr.width]`.
- `backend/app/Http/Middleware/SecurityHeaders.php`: `default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'`, alongside the four existing headers.
- Tests: `backend/tests/Feature/SecurityHeadersTest.php`; `content-security-policy.spec.ts` and `prerendered-html.spec.ts` under `frontend/src/server/`; specs for the two UI components; `frontend/e2e/content-security-policy.spec.ts`, which asserts the header, walks hydration, search, the command palette, the document viewer and admin realtime with zero `securitypolicyviolation` events, and checks that injected inline script is blocked on prerendered paths.
- See ADR-30 in `docs/DECISIONS.md` and BRAIN-009.

## Validation

Independent adversarial audit approved the final state after two rebases, on base `ec2ddfa`.

- Headers: CSP present, enforced and not report-only on `/`, `/search/?q=test`, `/login/`, `/c/...`, `/p/...`, `/commands/...`, the SSR 404, all eight prerendered `index.html` paths, `/index.csr.html`, upper-case variants such as `/INDEX.HTML` and `/Search/Index.Html`, and `/runtime-config.js`. API policy present on 200, 401, 404, 405 and 422 responses. Existing headers preserved.
- Configuration drives the policy: running the SSR with audit-chosen values produced `connect-src 'self' wss://ws-reauditoria.exemplo.test:7799 https://api-reauditoria.exemplo.test:9443`, with no `localhost`.
- Browser: an injected inline `<script>` and an `<img onerror>` are blocked with violations reported on rendered routes, prerendered paths, and upper-case variants. Public pages hydrate with zero violations and zero page errors, including the document viewer after the F-009 sanitizer landed.
- Real assets (`main-*.js`, `polyfills-*.js`, `styles-*.css`, `favicon.ico`, images, i18n JSON) are still served as files with `public, max-age=31536000`.
- Gates: Pest 88 passed; Pint PASS on 132 files; TypeScript and lint clean; Jest 40 passed; SSR build with 7 prerendered routes; E2E 6 passed and 1 failed; `composer audit` with no advisories; `npm audit --audit-level=critical` with 0 critical. No `skip`, `only`, `markTestSkipped`, `markTestIncomplete` or `todo` introduced.

## Remaining Work

- The single E2E failure is pre-existing and unrelated to the CSP: `portfolio-happy-paths.spec.ts` asserts `Run #` while the pt-BR UI renders `Execução #`. Tracked in `NEXT_ACTIONS.md`.
- The Pest gate wipes the development database, because the suite runs against `commandsphere` with `RefreshDatabase`. Run E2E before Pest and reseed afterwards. Tracked in `NEXT_ACTIONS.md`.
- A full E2E run needs the production SSR build (`node dist/frontend/server/server.mjs`) pointed at the development backend: `docker-compose.yml` serves the frontend through `ng serve`, which never runs `server.ts` and therefore sends no CSP, and `docker-compose.prod.yml` disables dev-login.
- On Windows only, `/index.html::$DATA` (an NTFS alternate data stream) is served as `application/octet-stream` without the policy. It is not exploitable, because `X-Content-Type-Options: nosniff` makes the browser download it, and the path does not exist on the Linux production image.
