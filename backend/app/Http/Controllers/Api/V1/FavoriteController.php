<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FavoriteResource;
use App\Models\Command;
use App\Models\Document;
use App\Models\Favorite;
use App\Models\User;
use App\Services\Discovery\DiscoveryAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $favorites = Favorite::query()
            ->with(['favoritable' => fn ($morphTo) => $morphTo->morphWith([
                Command::class => ['category', 'pluginVersion.plugin.community'],
                Document::class => ['pluginVersion.plugin.community'],
            ])])
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => FavoriteResource::collection($favorites)->resolve($request),
        ]);
    }

    public function store(Request $request, DiscoveryAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', 'string', 'in:command,document'],
            'id' => ['required', 'integer'],
        ]);
        $favoritable = $this->favoritable($access, $user, $data['type'], (int) $data['id']);

        $favorite = Favorite::query()->updateOrCreate([
            'user_id' => $user->id,
            'favoritable_type' => $favoritable::class,
            'favoritable_id' => $favoritable->id,
        ]);

        $favorite->load('favoritable');

        return response()->json([
            'data' => (new FavoriteResource($favorite))->resolve($request),
        ], 201);
    }

    public function destroy(Request $request, DiscoveryAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', 'string', 'in:command,document'],
            'id' => ['required', 'integer'],
        ]);
        $favoritable = $this->favoritable($access, $user, $data['type'], (int) $data['id']);

        Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $favoritable::class)
            ->where('favoritable_id', $favoritable->id)
            ->delete();

        return response()->json(status: 204);
    }

    private function favoritable(DiscoveryAccess $access, User $user, string $type, int $id): Model
    {
        $model = match ($type) {
            'command' => $access->commands($user)->whereKey($id)->first(),
            'document' => $access->documents($user)->whereKey($id)->first(),
            default => null,
        };

        if (! $model instanceof Model) {
            throw ValidationException::withMessages([
                'id' => ['The selected favorite target is invalid.'],
            ]);
        }

        return $model;
    }
}
