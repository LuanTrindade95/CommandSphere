<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevLoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return response()->json([
                'message' => 'Dev login is only available in local or testing environments.',
                'code' => 'auth.dev_login_disabled',
            ], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if ($user === null) {
            return response()->json([
                'message' => 'User not found.',
                'code' => 'auth.user_not_found',
            ], 404);
        }

        return response()->json([
            'token' => $user->createToken('dev-login')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
