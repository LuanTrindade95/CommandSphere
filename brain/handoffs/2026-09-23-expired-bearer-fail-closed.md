# Handoff - Expired Bearer Fails Closed

Date: 2026-09-23
Branch: `fix/expired-bearer-fail-closed`

## Scope

Closes F-015, the gap recorded while characterizing F-013: the public discovery endpoints resolve the optional bearer outside the `auth:sanctum` guard, and `PersonalAccessToken::findToken()` does not check `expires_at`, so an expired token still granted its owner's scope.

## Implemented

- `App\Services\Auth\OptionalBearerUserResolver` short-circuits between `findToken()` and the `tokenable` check: a resolved token whose `expires_at` is past returns an unpersisted `User`, the same empty scope as any non-empty bearer that fails to resolve. A token with no `expires_at` is untouched.
- The characterization case that locked the old behavior was inverted in the same commit, not deleted, and renamed to state the new contract. Two cases added: a token expired five seconds ago, and a token with no `expires_at` still resolving to its owner. One case pins the other seven header cases as unchanged.
- Decision recorded in ADR-32.

## Not changed, deliberately

`config('sanctum.expiration')` stays null, so a token issued without an explicit `expires_at` still never lapses, on every surface. Setting a lifetime changes login and session behavior through the guard; it is a product decision, queued in `NEXT_ACTIONS.md`. Routes, private middleware, policies, and `DiscoveryAccess` untouched.

## Validation

- Independent adversarial audit: APPROVED. It probed the resolver directly inside the container against a copy of main's class, case by case: only the expired row differs (owner to empty scope). The `expires_at` null edge was proven by execution, not by reading `?->`.
- The audit proved the test bites: with main's resolver and the branch's test file, exactly the two expired-token cases fail (`Expected response status code [404] but received 200`) and the other ten pass. So the inverted case is stronger, not looser: 404 on the owner's own plugin separates empty scope from both owner (200) and public (200).
- Assertion inventory main to branch: nothing shrank (`assertNotFound` 11 to 18, `assertOk` 17 to 20, the rest equal). No `skip`, `markTestSkipped`, `->todo(`, or `xit(`.
- Gates on the rebuilt image, hashes confirmed to match the branch: Pest 100 passed, Pint 134 files. Assertion counts drift between runs (564 and 566 observed) because of the Meilisearch `retry()` loop; the test count is the stable figure.
- A dead `use Laravel\Sanctum\PersonalAccessToken;` was removed from the test file. Verified dead on `main`: its only other occurrence there is inside a comment.

## Open

- Whether Sanctum tokens should expire at all. Until that is decided, this fix only reaches tokens that were given an explicit `expires_at`.
