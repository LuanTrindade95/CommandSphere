<?php

namespace App\Services\Auth;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class DiscordProvider extends AbstractProvider
{
    /**
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * @param  string|null  $state
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://discord.com/api/oauth2/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://discord.com/api/oauth2/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://discord.com/api/users/@me', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user)
    {
        $id = (string) Arr::get($user, 'id', '');
        $avatar = Arr::get($user, 'avatar');

        return (new User)->setRaw($user)->map([
            'id' => $id,
            'nickname' => Arr::get($user, 'username'),
            'name' => Arr::get($user, 'global_name') ?: Arr::get($user, 'username'),
            'email' => Arr::get($user, 'email'),
            'avatar' => is_string($avatar) && $avatar !== ''
                ? "https://cdn.discordapp.com/avatars/{$id}/{$avatar}.png?size=256"
                : null,
        ]);
    }
}
