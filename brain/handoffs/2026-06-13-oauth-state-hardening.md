# Handoff - Discord OAuth State Hardening

Date: 2026-06-13
Branch: `fix/oauth-state-hardening`

## Scope

Build-loop remediation phase for audit finding F-002.

## Implemented

- Added `App\Services\Auth\DiscordProvider` as an explicit Socialite OAuth2 provider for Discord.
- Replaced unsupported `Socialite::driver('discord')` calls with `Socialite::buildProvider(DiscordProvider::class, ...)`.
- Added a signed 10-minute state payload stored in `commandsphere_discord_oauth_state`.
- State cookie is httpOnly, `SameSite=Lax`, and uses configured session domain/secure behavior.
- Discord redirect now sends the generated `state` query parameter to Discord.
- Callback validates state before calling the Discord API.
- Callback returns `419` with `auth.oauth_state_invalid` for missing, invalid, tampered, mismatched, or expired state.
- Callback clears the state cookie on success and failure.
- Auth tests cover redirect state cookie, missing state rejection, and successful Discord callback.

## Validation

- `php -l app\Http\Controllers\Api\V1\Auth\DiscordAuthController.php` passed.
- `php -l app\Services\Auth\DiscordProvider.php` passed.
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor\bin\pest.bat tests\Feature\AuthApiTest.php --colors=never` passed: 8 tests, 42 assertions.
- `vendor\bin\pint.bat app\Http\Controllers\Api\V1\Auth\DiscordAuthController.php app\Services\Auth\DiscordProvider.php tests\Feature\AuthApiTest.php` applied style fixes.

## Notes

- The full backend suite still depends on local MySQL/Redis/Meilisearch configuration unless CI/test env is hardened.
- This phase uncovered and fixed the previously latent unsupported Discord Socialite driver issue.
