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

## BRAIN-007 - External Integrations Fail Closed With A Stable Failure Code

Decision: A call to an external service either produces the data it promised or ends the operation explicitly. An unexpected status, an unparseable body, a payload missing a required field, and a response flagged as incomplete are all failures, never an empty successful result. Every failure category carries a stable code persisted in the operation record, under the `code` key that consumers already read.

Consequences:

- A run that ingested nothing must be distinguishable from a source that legitimately has nothing. `success` with zero documents is a defect, not a state.
- An exception type raised by an integration must be caught at the boundary that owns the operation record. An exception escaping that boundary leaves the record stuck in `running` with no `finished_at`, which is worse than a wrong status because no operator sees it end.
- A failure code, once asserted by a test, is a contract. It is not renamed for aesthetics; new categories get clean literals while legacy ones keep their shape, as in ADR-27.
- Transient categories are matched by range (`>= 500`), not by an enumerated list, so unseen statuses stay covered.
- Failure messages carry identifiers and status only. Tokens, authorization headers, and response bodies never reach a log or an exception message.
- Failing closed raises the cost of a transient outage. Retry belongs to the operator-facing surface, deliberately scoped, never smuggled into the client as silent recovery.

## BRAIN-008 - Correlation IDs Are Validated At The Edge And Owned By The Record That Created Them

Decision: Every API request carries a correlation ID. A client-supplied `X-Request-Id` is accepted only when it matches a fully anchored allowlist pattern; anything else is discarded and replaced by a generated ID, never sanitized in place. An operation record stores the ID of the request that created it, and structured events go to the additive `telemetry` channel, never to the default channel. See ADR-29.

Consequences:

- Anchoring uses the PCRE `D` modifier or `\z`. A bare `$` matches before a trailing newline, so a pattern without `D` accepts `"id\n"` and echoes it raw into response headers and persisted columns. Tests for a validation pattern cover trailing `\n`, `\r`, and `\r\n`, not only embedded line breaks.
- A reused operation record is never rewritten with a later caller's ID. The later caller's ID appears only in its own event, which references the reused record.
- The correlation ID is added as a field on existing log entries, not as a new entry, because tests assert log entries by index.
- Telemetry context carries identifiers, counts, statuses, and failure codes only. Request payloads, headers, signatures, tokens, and Markdown content never reach it.
- Failure codes in events are read from the `code` key already persisted by BRAIN-007, never recomputed.

## BRAIN-009 - Every Response Carries An Enforced CSP With No Inline Exceptions

Decision: Every HTML and JSON response carries an enforced Content-Security-Policy, never a report-only one. The SSR policy admits no `unsafe-inline` and no `unsafe-eval`; inline styles are allowed only through a per-request nonce, and the origins in `connect-src` come from the runtime configuration, never from a fixed host. See ADR-30.

Consequences:

- A response path that bypasses the Angular render still carries the policy: static file serving, a handler registered before the renderer, a prerendered document. A policy that covers only the rendered routes is the failure this decision exists to prevent.
- Matching a request path against a file extension is case-insensitive. A case-insensitive filesystem serves `/INDEX.HTML` from the same file as `/index.html`, so a case-sensitive check reopens the bypass.
- Headers that must reach every API response are set by global middleware, not by a route group. Requests that match no route never run group middleware, so a header set there is absent from 404 and 405 responses.
- Components do not use `[style.*]` bindings or `style=""` attributes. Enumerable values become classes; continuous values become SVG geometry attributes. `style-src-attr 'unsafe-inline'` requires a recorded decision.
- HTML carrying a nonce is never cacheable. A cache or proxy placed in front of the SSR must not cache HTML or rewrite the header.
- The policy is proven in a real browser, not only by header assertions: an injected inline script and an inline event handler must both be blocked and reported as violations.

## BRAIN-010 - `main` Moves While A Task Runs, So Shared Numbers And Verified States Are Claimed Late

Decision: Several sessions work this repository at once and merge into `main` during a task. Anything shared across branches is therefore read from `origin/main` at the moment it is written, and the state that ships is the state that was verified.

Consequences:

- An ADR number is allocated by reading `docs/DECISIONS.md` on `origin/main` immediately before writing, never reserved at the start of a task. The same holds for `BRAIN-NNN` and for any other sequence.
- A branch is rebased onto `origin/main` before publishing, and the gates run again on the rebased base. Approval granted on an earlier base does not carry over on its own: confirm that the change's own diff is unchanged and that the new base did not break it.
- Before opening or merging a pull request, `git fetch` again. A rebase that was current an hour ago may not be.
- When an edit to `brain/` or `docs/` is the closing step, re-read the file on the current base. Another session may have added a section where one is about to be written.

## BRAIN-011 - A Scope Rule Has One Implementation, And A Refactor Proves It By Characterization First

Decision: The rule that decides which scope a request gets exists once. When the same rule is found duplicated, the extraction is a pure move: characterization tests are written and committed against the unmodified code first, and the same tests, unchanged, must pass after the extraction. A behavior the refactor discovers to be wrong is reported and locked as-is, never corrected inside the same change. See ADR-31.

Consequences:

- The test commit comes before the refactor commit, so the tests cannot be retrofitted to the new behavior. An auditor verifies the order with `git log` and the test blob being byte-identical across both commits.
- Equivalence is argued from the extracted body being character-identical to the original and the call sites being one-to-one substitutions, not from the tests alone.
- A gap found while characterizing becomes a finding with a test that names it as pre-existing, plus a queue entry. Silently fixing it inside a refactor destroys the evidence that responses did not change.
- What the audit item says a piece of code does is a claim, not the contract. The contract is what the code does, read from the source before the tests are written.
