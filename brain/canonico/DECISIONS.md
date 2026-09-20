# Brain Decisions

This file records decisions about how the Brain should guide future work. Product architecture decisions still belong in `docs/DECISIONS.md`.

## BRAIN-001 - Brain Mirrors The Implemented Product

Decision: The Brain documents the real implemented CommandSphere system and must not become an aspirational spec detached from source code.

Consequences:

- When source code, tests, and Brain disagree, inspect code and tests first.
- Update Brain only after verifying the actual implementation.
- Future roadmap items belong in `NEXT_ACTIONS.md`, not `CURRENT_STATE.md`.

## BRAIN-002 - Keep Canonical State Separate From Historical Progress

Decision: `brain/canonico/CURRENT_STATE.md` describes the present system. `docs/PROGRESS.md` remains the historical phase log.

Consequences:

- Do not copy every commit or old phase note into the Brain.
- Use Brain for fast onboarding and next implementation decisions.
- Use `docs/PROGRESS.md` when historical phase evidence is needed.

## BRAIN-003 - Improvements Must Preserve Portfolio Signal

Decision: New work must be evaluated by product credibility and senior engineering signal, not just feature count.

Consequences:

- Prefer improvements that show domain reasoning, operational maturity, performance, security, or UX polish.
- Avoid generic CRUD additions unless they unlock a meaningful workflow.
- Every substantial feature should include user-facing quality, tests, and documentation.

## BRAIN-004 - Brain Uses CommandSphere-Specific Gates

Decision: This repository does not use the generic Supabase/Deno build-loop gate set. The Brain defines a CommandSphere gate set for Laravel, Angular, Docker, Meilisearch, Reverb, and Horizon.

Consequences:

- Future build-loop work must adapt gates to this repo.
- Skipped gates must be explicitly reported as `PENDING`.
- Documentation-only changes can validate through factual review and diff inspection, but product changes need executable gates.

## BRAIN-005 - Public Discovery And Private Operations Stay Separate

Decision: Future features must preserve the existing split between public discovery read models and authenticated operational actions.

Consequences:

- Public SSR pages may read catalog/search/plugin/command data.
- Mutations, favorites, analytics actions, ingestion controls, and operational data require authenticated permission checks.
- Invalid authenticated-looking requests must not silently fall back to anonymous scope.

## BRAIN-006 - Dependency Advisories Are Closed Within The Current Major

Decision: Security advisories are closed by updating locks inside the current majors, and by npm `overrides` when a transitive dependency is the only blocker. Audit gates are never weakened to make CI pass: no `--audit-level` downgrade, no `continue-on-error`, no `audit.ignore`, no `npm audit fix --force`.

Consequences:

- `critical` blocks the pipeline; `high` and `moderate` without a non-major fix are recorded as risk under ADR-24 and never reported as corrected.
- An override that leaves the range declared by its dependent needs a recorded ADR stating the verified facts that justify it, as in ADR-26.
- Toolchain major upgrades stay a deliberate, separately planned task.
