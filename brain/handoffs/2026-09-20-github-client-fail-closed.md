# Handoff - GitHub Client Fail-Closed

Date: 2026-09-20
Branch: `fix/github-client-fail-closed`

## Scope

Audited remediation item C2: close F-006. The GitHub client only mapped 403 and 404, so every other failure reached the pipeline as an ordinary response and produced a misleading outcome.

## Implemented

- `App\Exceptions\GitHubClientException`, abstract, exposing `failureCode()`. Six concrete subtypes plus the two pre-existing exceptions reparented onto it.
- `HttpGitHubClient` fails closed on every non-success status and validates the payload: non-JSON body, body without `tree`, `truncated: true`, invalid base64, and unsupported encoding all raise instead of degrading to an empty or partial result.
- `IngestionService::run()` catches the whole `GitHubClientException` hierarchy. This closed a second fail-open path that F-006 did not describe: any other exception previously escaped `run()` and left the `IngestionRun` stuck in `running`, with no `finished_at` and no log.
- Codes: `git_hub_rate_limit_exception`, `github_authentication_failed`, `git_hub_repository_not_found_exception`, `github_validation_failed`, `github_transient_error`, `github_malformed_response`, `github_tree_truncated`. Taxonomy and trade-offs in ADR-27.
- A 403 is classified by the rate-limit header; a permission denial no longer reports as rate limiting.
- `backend/tests/Feature/GitHubClientFailClosedTest.php`: one case per category, a 304/ETag regression, and an end-to-end case proving a transient failure ends the run as `failed` instead of hanging it.

## Not changed

`App\Contracts\GitHubClient`, `FixtureGitHubClient`, `IngestionService::fail()` signature, `IngestionRun.log` entry shape, and every pre-existing test expectation, including the literal `git_hub_rate_limit_exception` asserted in `IngestionPipelineTest`. No retry or backoff: that stays in Priority 8, and failing closed makes the operator-facing retry action there more necessary, not less.

## Validation

- Independent adversarial audit: APPROVED. The auditor rebuilt all 16 scenarios with its own `Http::fake` harness, ran `IngestionService::run()` end to end, and read `status` and `log` from the database. No error scenario finished `success`, none finished `success` with zero documents, and no two distinct categories shared a code.
- The auditor first verified that the container image matched the branch, by comparing `sha1sum` of all 73 `.php` files under `app/` and `tests/` inside and outside the container. The `backend` service has no bind mount, so gates run against the built image.
- 502, absent from the executor's own table, is covered: the implementation matches `>= 500` rather than a status list, confirmed empirically.
- Token leak check with a fictitious token across `IngestionRun.log`, `storage/logs`, exception messages, and the branch diff: no occurrence of the token, `Bearer`, or `Authorization`.
- Test count 33 to 50, no test removed, no `skip`, no weakened assertion.
- Gates on the four implementation commits: Pest 50 passed, Pint 122 files.

## Gate trap found while closing

The `backend` service has no bind mount, so `docker compose exec backend ./vendor/bin/pest` runs the code baked into the image, not the working tree. After the dead-helper cleanup commit `2f4b706` the image still contained the removed symbol while the disk did not, which means a gate run right after an edit can report success for code that was never executed. Rebuild with `docker compose up -d --build backend` before trusting a gate, and confirm the image matches the branch — the audit of this item did exactly that with `sha1sum` across `app/` and `tests/`.

The final gates were re-run against a rebuilt image covering all six commits: Pest 50 passed (233 assertions), Pint 122 files. Assertion counts drift between runs (249, 239, 233 observed); the stable figure is the test count.

## Open

- Priority 8 retry controls now matter more: a momentary GitHub outage fails the whole run by design.
