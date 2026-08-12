<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CommunityMembershipService;
use App\Services\Auth\DiscordProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class DiscordAuthController extends Controller
{
    private const STATE_COOKIE = 'commandsphere_discord_oauth_state';

    private const STATE_TTL_MINUTES = 10;

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);

        return $this->discordProvider()
            ->scopes(['identify', 'email'])
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->withCookie(cookie(
                self::STATE_COOKIE,
                $this->signedStateCookie($state),
                self::STATE_TTL_MINUTES,
                '/',
                $this->cookieDomain(),
                $this->cookieSecure($request),
                true,
                false,
                'Lax',
            ));
    }

    public function callback(Request $request, CommunityMembershipService $memberships): JsonResponse
    {
        if (! $this->hasValidState($request->query('state'), $request->cookie(self::STATE_COOKIE))) {
            return response()
                ->json([
                    'message' => 'Invalid OAuth state.',
                    'code' => 'auth.oauth_state_invalid',
                ], 419)
                ->withoutCookie(self::STATE_COOKIE, '/', $this->cookieDomain());
        }

        $discordUser = $this->discordProvider()
            ->stateless()
            ->user();

        $discordId = (string) $discordUser->getId();
        $email = $discordUser->getEmail() ?: 'discord-'.$discordId.'@users.commandsphere.local';
        $username = $discordUser->getNickname() ?: Str::before($email, '@');

        $user = User::query()
            ->where('discord_id', $discordId)
            ->orWhere('email', $email)
            ->first();

        $payload = [
            'name' => $discordUser->getName() ?: $username,
            'email' => $email,
            'discord_id' => $discordId,
            'username' => $username,
            'avatar' => $discordUser->getAvatar(),
        ];

        if ($user === null) {
            $user = User::query()->create($payload + [
                'password' => Str::password(48),
            ]);
        } else {
            $user->forceFill($payload)->save();
        }

        $memberships->attachToDefaultCommunityAsMember($user);

        return response()
            ->json([
                'token' => $user->createToken('discord-oauth')->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ])
            ->withoutCookie(self::STATE_COOKIE, '/', $this->cookieDomain());
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'discord_id' => $user->discord_id,
            'username' => $user->username,
            'avatar' => $user->avatar,
        ];
    }

    private function discordProvider(): AbstractProvider
    {
        return Socialite::buildProvider(DiscordProvider::class, [
            'client_id' => config('services.discord.client_id'),
            'client_secret' => config('services.discord.client_secret'),
            'redirect' => config('services.discord.redirect'),
        ]);
    }

    private function signedStateCookie(string $state): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'state' => $state,
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    private function hasValidState(mixed $state, ?string $cookie): bool
    {
        if (! is_string($state) || $state === '' || $cookie === null || ! str_contains($cookie, '.')) {
            return false;
        }

        [$payload, $signature] = explode('.', $cookie, 2);

        if (! hash_equals($this->signature($payload), $signature)) {
            return false;
        }

        try {
            $decoded = json_decode($this->base64UrlDecode($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($decoded)
            || ! isset($decoded['state'], $decoded['expires_at'])
            || ! is_string($decoded['state'])
            || ! is_int($decoded['expires_at'])) {
            return false;
        }

        return $decoded['expires_at'] >= now()->timestamp
            && hash_equals($decoded['state'], $state);
    }

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        $padded = $padding === 0 ? $value : $value.str_repeat('=', 4 - $padding);

        return (string) base64_decode(strtr($padded, '-_', '+/'), true);
    }

    private function cookieDomain(): ?string
    {
        $domain = config('session.domain');

        return is_string($domain) && $domain !== '' && $domain !== 'null' ? $domain : null;
    }

    private function cookieSecure(Request $request): bool
    {
        $secure = config('session.secure');

        return is_bool($secure) ? $secure : $request->isSecure();
    }
}
