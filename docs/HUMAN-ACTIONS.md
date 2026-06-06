# Human Actions

## Discord OAuth credentials

Arthur must configure the Discord OAuth application credentials in `backend/.env` before using the real OAuth flow:

```env
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI="${APP_URL}/api/v1/auth/discord/callback"
```

For local development and CI, `/api/v1/auth/dev-login` remains available only when `APP_ENV` is `local` or `testing`, so tests and backend work do not depend on external Discord credentials.
