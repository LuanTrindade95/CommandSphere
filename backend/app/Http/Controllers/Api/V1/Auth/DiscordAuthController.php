<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CommunityMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class DiscordAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->stateless()
            ->redirect();
    }

    public function callback(CommunityMembershipService $memberships): JsonResponse
    {
        $discordUser = Socialite::driver('discord')
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

        return response()->json([
            'token' => $user->createToken('discord-oauth')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
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
}
