# Handoff - Server-Side HTML Sanitization

Date: 2026-09-20
Branch: `fix/server-side-html-sanitization`

## Scope

Audit finding F-009: `content_html` derived from third-party Markdown was stored and returned without server-side sanitization, with the Angular renderer as the only barrier.

## Implemented

- `App\Services\Markdown\HtmlSanitizer`: tag/attribute allowlist over `DOMDocument`, no new dependency. `href`/`src` accept only `http`, `https`, `mailto`, and relative paths; control bytes are stripped before the scheme check; entity decoding and case folding are handled by the DOM layer.
- `MarkdownParser` converts with `html_input=escape` and `allow_unsafe_links=false`, then sanitizes before persistence.
- `Document` exposes a `contentHtml()` accessor that sanitizes on every read without rewriting the stored column, which is what covers rows ingested before the fix.
- Tests: `tests/Unit/Services/Markdown/HtmlSanitizerTest.php` (19 malicious cases plus 8 legitimate ones) and `tests/Feature/MarkdownSanitizationTest.php` (full parse pipeline, and a legacy row written straight to the database served sanitized by the API).
- See ADR-26 in `docs/DECISIONS.md`.

## Validation

Adversarial audit ran against an isolated `commandsphere_audit` database, which was dropped afterwards; the development database received no migrate or reset.

- 19 payloads ingested through the real pipeline and read back through `GET /api/v1/documents/{id}`: 0 surviving executable tags, `on*` handlers, or `javascript:`/`data:` schemes.
- Second route `GET /api/v1/plugins/{slug}/versions/{version}/documents` checked against the "sanitized in one route only" failure mode: 38 documents, 0 failures.
- Legacy rows inserted through the query builder: stored column stays malicious, API output is sanitized.
- Legitimate rendering: 18/18 structure checks present, code blocks escaped rather than live tags.
- Pest 55 passed; Pint PASS on 118 files; no `skip`/`only`/`todo` in `backend/tests` or `frontend/src`.
- `composer audit` 22 advisories in 4 packages and 2 Jest failures in `runtime-config.spec.ts` are byte-identical to the `a3a8299` baseline; `composer.json`, `composer.lock`, and `frontend/` are unchanged by this branch.

## Remaining Work

- Deploy step, not a code defect: `DiscoveryCache` holds resolved payloads for 300 seconds, so entries cached before the deploy keep serving unsanitized HTML until they expire. Invalidate the cache on deploy, per `docs/HUMAN-ACTIONS.md`.
- Dependency advisories (`league/commonmark` 2.8.2 including CVE-2026-71478, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `phpseclib/phpseclib`) are tracked in `NEXT_ACTIONS.md`. The sanitizer neutralizes that bypass class on its own, so this is hygiene rather than an open hole.
- The `runtime-config.spec.ts` Jest failures come from Compose environment variables leaking into the test `process.env` and are unrelated to Markdown.
- Content Security Policy remains open under its own audit finding and would be the next layer above this one.
