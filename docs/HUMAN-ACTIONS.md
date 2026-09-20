# Human Actions

## Discord OAuth credentials

The environment operator must configure the Discord OAuth application credentials in `backend/.env` before using the real OAuth flow:

```env
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI="${APP_URL}/api/v1/auth/discord/callback"
```

For local development and CI, `/api/v1/auth/dev-login` remains available only when `APP_ENV` is `local` or `testing`, so tests and backend work do not depend on external Discord credentials.

## Invalidate the discovery cache when deploying ADR-28

`App\Services\Discovery\DiscoveryCache` stores already-resolved discovery payloads for 300 seconds, and `CatalogController` serves `document` and `versionDocuments` through it. Entries cached before the server-side sanitization deploy keep returning unsanitized `content_html` until they expire.

After deploying the sanitization change, invalidate the discovery cache instead of waiting out the window:

```bash
docker compose exec -T backend php artisan tinker --execute="app(App\Services\Discovery\DiscoveryCache::class)->invalidate();"
```

This is a one-time deploy step. New responses are sanitized on read, so the cache repopulates with sanitized payloads.
