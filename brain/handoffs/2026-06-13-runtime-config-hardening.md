# Handoff - Runtime Public Configuration Hardening

Date: 2026-06-13
Branch: `fix/runtime-production-config`

## Scope

Build-loop remediation phase for audit findings F-001, F-010, and F-012.

## Implemented

- Added browser-safe runtime config at `frontend/public/runtime-config.js`.
- Added SSR `/runtime-config.js` endpoint generated from `COMMANDSPHERE_*` environment variables.
- Centralized Angular runtime config through `COMMANDSPHERE_RUNTIME_CONFIG`.
- Replaced hardcoded browser API URL with runtime API base URL.
- Replaced Reverb localStorage config with runtime Reverb config.
- Made SEO service use configured public origin instead of document origin.
- Made SSR use `COMMANDSPHERE_PUBLIC_ORIGIN` for render URL generation.
- Made SSR allowed hosts configurable through `COMMANDSPHERE_ALLOWED_HOSTS`.
- Added SSR post-processing for canonical URL, Open Graph URL/image, Twitter image, and JSON-LD URL.
- Documented public frontend/SSR variables in `.env.example`, `README.md`, `docker-compose.yml`, and `docker-compose.prod.yml`.

## Validation

- `npm.cmd run lint` passed.
- `npm.cmd test -- --runInBand` passed: 10 suites, 15 tests.
- `npm.cmd run build` passed.
- SSR artifact smoke passed with `COMMANDSPHERE_PUBLIC_ORIGIN=https://commandsphere.example`.
- Smoke confirmed `/runtime-config.js`, canonical URL, Open Graph URL/image, and JSON-LD use the configured public origin.
- Smoke confirmed generated HTML does not contain `http://localhost:4200` or `http://ng-localhost`.
- `git diff --check` passed.
- `npm.cmd audit --audit-level=critical` completed without critical findings. Existing moderate/high Angular toolchain advisories remain tracked.

## Remaining Gates

- Docker production smoke with hydrated browser API calls.
- Reverb websocket integration validation through the runtime public host/port/scheme.
- CI/service-backed hardening from Priority 4.
