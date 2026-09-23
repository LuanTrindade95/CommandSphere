# Handoff - Optional Bearer User Resolver

Date: 2026-09-23
Branch: `refactor/optional-bearer-user-resolver`

## Scope

Remediation item C6: close F-013. `CatalogController::currentUser()` and `SearchController::currentUser()` held the same optional-user resolution, character-identical (486 characters normalized), used eight times in catalog and once in search. Pure refactor: no response, route, middleware, policy, or `DiscoveryAccess` change.

## Implemented

- `App\Services\Auth\OptionalBearerUserResolver`, a stateless function of `Request`, injected by constructor into both controllers. Both private methods removed. The body is character-identical to the original.
- `backend/tests/Feature/OptionalBearerUserResolutionTest.php`: 8 header cases (no header, `Authorization: Basic`, empty `Bearer`, valid member token, garbage token, revoked token, valid token without membership, expired token) across the 8 public catalog and search endpoints, asserting status, item counts, and absence of private-community data.
- Contract recorded in ADR-31 and BRAIN-011.

## Order of work, and why it matters

Commit `05de965` contains only the test file, on a tree whose controllers are still main's. Commit `8e1bdc4` contains the refactor. The test blob is byte-identical across both commits, so the characterization cannot have been retrofitted to the new behavior. An auditor verifies this with `git log`/`git diff` rather than trusting the report.

## Not changed, deliberately

- A non-bearer `Authorization` header and an empty `Bearer` resolve to the public scope, not the empty scope. The guard clause returns `null` as soon as `bearerToken()` is null or empty, so the empty scope exists only for a non-empty bearer that fails to resolve. The F-013 text claimed otherwise; the code is the contract.
- An expired token is still accepted as its owner: `findToken()` ignores `expires_at` and `sanctum.expiration` is null. Tracked as F-015, locked by a test named as a pre-existing gap, and explained in the resolver docblock. Fixing it changes endpoint responses and belongs to its own branch.

## Validation

- Independent adversarial audit: APPROVED. The auditor compared the three bodies (main's two copies and the branch resolver) whitespace-normalized and found them identical at 486 characters, probed the four header classes inside the running container, and confirmed `DiscoveryAccess::communityIds()` maps `null` to every community and an unsaved `User` to `[]`.
- Auditor limitation, stated by it: it could not re-execute the suite at `05de965`, because the `backend` service has no bind mount and the image could not be mutated. Equivalence rests on the character identity plus the unchanged test blob.
- Gates on the branch before merging `main`: Pest 95 passed, Pint 133 files. After merging `0f07c74` (CSP) and rebuilding the backend image: Pest 97 passed (544 assertions), Pint 134 files. Frontend gates were not re-run here, because this branch touches backend only; they are `PENDING` on this branch and `VALIDATED` on `main` for the CSP work it merged. The auditor measured 528 assertions against the executor's 530; the assertion count drifts because the Meilisearch `retry()` loop in the search test emits a variable number. The stable figure is the test count.
- No `skip`, `markTestSkipped`, `->todo(`, or `xit(` anywhere under `backend/tests`.

## Gate trap, still current

The `backend` service has no bind mount, so `docker compose exec backend ./vendor/bin/pest` runs the image, not the working tree. Rebuild with `docker compose up -d --build backend` before trusting a gate, and confirm the image matches the branch by hashing the files inside and outside the container.

## Open

- F-015, expired token accepted on public discovery endpoints. Queued in `NEXT_ACTIONS.md`.
- The public-versus-empty scope asymmetry for non-bearer `Authorization` headers is recorded as current behavior. If it should be empty scope instead, that is a functional decision, not a refactor.
