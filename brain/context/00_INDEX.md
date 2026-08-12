# Brain Context Index

Use this index to decide which context files to read for a new task.

## Canonical Files

- `brain/CLAUDE.md` - Brain entrypoint, operating rules, and gate set.
- `brain/canonico/CURRENT_STATE.md` - factual present state of the product.
- `brain/canonico/NEXT_ACTIONS.md` - prioritized improvement queue.
- `brain/canonico/DECISIONS.md` - decisions about Brain usage and future work discipline.

## Context Files

- `brain/audits/2026-06-13-system-audit.md`
  - Read before hardening, production-readiness, security, telemetry, CI, ingestion, analytics, OAuth, or deployment work.
- `brain/context/01_PRODUCT_AND_DOMAIN.md`
  - Read when changing product scope, domain model, onboarding, discovery workflows, or portfolio positioning.
- `brain/context/02_IMPLEMENTATION_MAP.md`
  - Read when touching backend services, frontend routes, API contracts, ingestion, search, realtime, auth, or Docker.
- `brain/context/03_QUALITY_AND_OPERATIONS.md`
  - Read when changing tests, CI, release readiness, Docker, security, deployment, observability, or operational docs.
- `brain/context/04_IMPROVEMENT_PLAYBOOK.md`
  - Read before starting a new feature or refactor to shape the implementation plan.

## Repository Docs

- `README.md` - high-level product, setup, architecture, quality, screenshots.
- `docs/VISION.md` - original product vision, MVP scope, domain, risks, brand kit.
- `docs/DECISIONS.md` - product ADRs. Update this for architecture decisions.
- `docs/PROGRESS.md` - phase history and commit evidence.
- `docs/HUMAN-ACTIONS.md` - operator actions and external credentials.

## Quick Routing

- Auth/session change: read `CURRENT_STATE`, `02_IMPLEMENTATION_MAP`, `03_QUALITY_AND_OPERATIONS`, ADR-07, ADR-08, ADR-15, ADR-16.
- Ingestion/parser change: read `CURRENT_STATE`, `01_PRODUCT_AND_DOMAIN`, `02_IMPLEMENTATION_MAP`, ADR-09, ADR-10, ADR-11.
- Search/discovery change: read `CURRENT_STATE`, `02_IMPLEMENTATION_MAP`, ADR-12, ADR-13, ADR-23.
- Realtime/admin operations change: read `CURRENT_STATE`, `02_IMPLEMENTATION_MAP`, `03_QUALITY_AND_OPERATIONS`, ADR-20, ADR-21, ADR-22.
- Frontend UX change: read `01_PRODUCT_AND_DOMAIN`, `02_IMPLEMENTATION_MAP`, ADR-02, ADR-03, ADR-17, ADR-18, ADR-19.
- Deployment/hardening change: read `03_QUALITY_AND_OPERATIONS`, ADR-24, ADR-25.
- Security/telemetry audit follow-up: read `brain/audits/2026-06-13-system-audit.md` first, then the area-specific context above.
