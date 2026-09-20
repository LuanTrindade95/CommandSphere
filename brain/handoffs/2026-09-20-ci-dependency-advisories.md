# Handoff - CI Dependency Advisories

Date: 2026-09-20
Branch: `fix/ci-dependency-advisories`, merged into `main` through PR #5 (merge commit `8623fea`)

## Scope

Audited remediation item C1: bring the `main` CI back to green. Both jobs had been failing on dependency audit steps since the merge of PR #1, with no failing test.

## Implemented

- `backend/composer.lock` updated inside the current majors: `guzzlehttp/guzzle 7.11.0 -> 7.15.5`, `guzzlehttp/psr7 2.11.0 -> 2.13.1`, `league/commonmark 2.8.2 -> 2.10.1`, `phpseclib/phpseclib 3.0.52 -> 3.0.57`. `backend/composer.json` untouched, 22 advisories closed.
- `frontend/package.json` gained the overrides `"pacote": "20.0.1"` and `"tar": "^7.5.21"`, closing the only `critical` (`tar`, path traversal). Rationale and verified facts are recorded in ADR-26.
- No CI gate was weakened: `.github/workflows/` has no diff, `--audit-level` stays `critical`, no `continue-on-error`, no `audit.ignore`, no test skipped.

## Validation

- Clean install in a CI-parity container (PHP 8.3, Node 22): `composer install` + `composer audit` exit 0, "No security vulnerability advisories found."; `npm ci` + `npm audit --audit-level=critical` exit 0.
- Pint `115 files`, Pest `33 passed (191 assertions)`, `tsc --noEmit`, lint, Jest `15/15`, SSR build with `server/` and `prerendered-routes.json`.
- `IngestionPipelineTest` passes in all 5 cases, confirming the CommonMark bump does not change `App\Services\Markdown\MarkdownParser` or ingestion behaviour.
- CI on the branch: run `35474423377`, both jobs `success` on `3c1095b`. CI on `main` after the merge: run `35485832903`, both jobs `success` on `8623fea`.
- Independent adversarial audit: APPROVED on all 9 checklist items, including an own-hands clean install, the override facts through `npm ls`, and the run SHA against the remote HEAD.

## Remaining Work

Tracked as Priority 12 in `brain/canonico/NEXT_ACTIONS.md`:

- Re-evaluate and eventually drop the `pacote` and `tar` overrides at the next Angular toolchain upgrade.
- Close `@sigstore/sign` and `@sigstore/verify`, the only remaining advisories with `fixAvailable: true`.
- Plan the Angular major upgrade that closes the remaining runtime and toolchain advisories.
- Investigate the Pest warnings on the GitHub runner (`32 warnings, 1 passed` there versus `33 passed` in the dev container).
- Update `actions/checkout@v4` and `actions/setup-node@v4`, and plan the `ubuntu-latest` migration to Ubuntu 26.
