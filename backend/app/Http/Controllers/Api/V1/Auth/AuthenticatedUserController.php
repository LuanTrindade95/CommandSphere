<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\User;
use Database\Seeders\CommunityPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticatedUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('communities');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'discord_id' => $user->discord_id,
                'username' => $user->username,
                'avatar' => $user->avatar,
            ],
            'communities' => $user->communities
                ->map(fn (Community $community): array => [
                    'id' => $community->id,
                    'name' => $community->name,
                    'slug' => $community->slug,
                    'role' => $community->pivot->role,
                    'permissions' => collect(CommunityPermissionSeeder::PERMISSIONS)
                        ->mapWithKeys(fn (string $permission): array => [
                            $permission => $user->hasCommunityPermission($community, $permission),
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
            'code' => 'auth.logged_out',
        ]);
    }
}
