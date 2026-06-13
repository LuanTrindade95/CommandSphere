# Improvement Playbook

Use this playbook before implementing a new CommandSphere improvement.

## 1. Classify The Work

Choose one primary track:

- Product feature: adds or changes user-facing behavior.
- Architecture/refactor: improves structure without changing expected behavior.
- UX polish: improves layout, interaction, responsiveness, or visual quality.
- Operations/hardening: improves deployment, observability, security, or runtime behavior.
- Documentation: improves understanding without product code changes.

If the work spans tracks, define the main deliverable and keep unrelated changes out.

## 2. Read The Existing Surface

Before editing:

- Inspect `git status --short --branch`.
- Read the Brain files listed in `brain/CLAUDE.md`.
- Read source files for the exact area.
- Read related tests.
- Read existing docs/ADRs for decisions that constrain the change.

Do not implement from memory when the source tree can answer the question.

## 3. Define The Senior-Level Outcome

Every improvement should answer:

- What user or operator problem does this solve?
- Which existing pattern does it reuse?
- What business rule or architecture invariant must be preserved?
- What is the performance/security/maintainability impact?
- What evidence will prove it works?

Avoid building generic CRUD screens unless the workflow needs them.

## 4. Plan The Implementation Boundary

For backend work:

- Start with route/API contract and policy implications.
- Keep controllers thin.
- Put business workflow in services/jobs where useful.
- Use API Resources for response shape.
- Add Pest tests for business rules, permissions, failures, and idempotency.

For frontend work:

- Start with route/workflow and UX states.
- Keep components focused.
- Use core services for API state and shared UI primitives for presentation.
- Preserve SSR safety.
- Add or update i18n keys.
- Add Jest and Playwright coverage where behavior is meaningful.

For cross-stack work:

- Lock the API contract first.
- Build backend tests before UI assumptions harden.
- Then wire frontend with loading/error/empty states.

## 5. Validate

Run the relevant CommandSphere gate set from `brain/CLAUDE.md`.

Report results as:

- `VALIDATED`: command ran and passed.
- `FAILED`: command ran and failed; include the error summary.
- `PENDING`: command did not run; include why and the exact command.

Never imply a gate passed because a narrower command passed.

## 6. Update Documentation

At close:

- Update `README.md` if setup, architecture, status, or user-facing capability changed.
- Update `docs/DECISIONS.md` if a decision has long-term consequences.
- Update `docs/HUMAN-ACTIONS.md` if an operator must do something.
- Update Brain current state and next actions.
- Add a handoff note for multi-step or partially validated work.

## 7. Residue Check

Before final response:

- Inspect `git status --short`.
- Ensure changed files match scope.
- Do not stage with `git add .`.
- Do not commit/push/open PR unless explicitly requested.
- Mention remaining risks and pending gates.
