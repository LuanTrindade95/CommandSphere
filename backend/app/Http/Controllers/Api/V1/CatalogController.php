<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandResource;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PluginResource;
use App\Models\Community;
use App\Models\Document;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Models\User;
use App\Services\Discovery\DiscoveryAccess;
use App\Services\Discovery\DiscoveryCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class CatalogController extends Controller
{
    public function __construct(
        private readonly DiscoveryAccess $access,
        private readonly DiscoveryCache $cache,
    ) {}

    public function communities(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'communities:index',
                fn (): array => CommunityResource::collection(
                    $this->access->communities($user)->orderBy('name')->get(),
                )->resolve($request),
            ),
        ]);
    }

    public function community(Request $request, Community $community): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->access->ensureCommunity($user, $community);

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'communities:'.$community->slug,
                fn (): array => (new CommunityResource($community))->resolve($request),
            ),
        ]);
    }

    public function plugins(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $community = $request->query('community');

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'plugins:index:community='.(is_string($community) ? $community : 'all'),
                fn (): array => PluginResource::collection(
                    $this->access->plugins($user)
                        ->with(['community', 'versions' => fn ($query) => $query->latest('published_at')])
                        ->when(is_string($community) && $community !== '', fn (Builder $query): Builder => $query
                            ->whereHas('community', fn (Builder $communityQuery): Builder => $communityQuery->where('slug', $community)))
                        ->orderBy('name')
                        ->get(),
                )->resolve($request),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $data = $request->validate([
            'community_id' => ['required', 'integer', 'exists:communities,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('plugins', 'slug')
                    ->where(fn ($query) => $query->where('community_id', $request->integer('community_id'))),
            ],
            'description' => ['nullable', 'string'],
            'github_repo' => ['required', 'string', 'max:255'],
            'docs_path' => ['required', 'string', 'max:255'],
            'default_branch' => ['required', 'string', 'max:255'],
        ]);

        $community = Community::query()->whereKey($data['community_id'])->firstOrFail();
        $this->access->ensureCommunity($user, $community);

        Gate::authorize('manage', [Plugin::class, $community]);

        $plugin = Plugin::query()->create([
            'community_id' => $community->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'repository_url' => $data['github_repo'],
            'documentation_path' => $data['docs_path'],
            'default_branch' => $data['default_branch'],
        ]);

        $this->cache->invalidate();

        return response()->json([
            'data' => (new PluginResource($plugin->load(['community', 'versions'])))->resolve($request),
        ], 201);
    }

    public function plugin(Request $request, string $slug): JsonResponse
    {
        $user = $this->currentUser($request);
        $plugin = $this->pluginBySlug($request, $user, $slug)
            ->with(['community', 'versions' => fn ($query) => $query->latest('published_at')])
            ->firstOrFail();

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'plugins:'.$plugin->community_id.':'.$plugin->slug,
                fn (): array => (new PluginResource($plugin))->resolve($request),
            ),
        ]);
    }

    public function versionDocuments(Request $request, string $slug, string $version): JsonResponse
    {
        $user = $this->currentUser($request);
        $plugin = $this->pluginBySlug($request, $user, $slug)->firstOrFail();
        $pluginVersion = PluginVersion::query()
            ->where('plugin_id', $plugin->id)
            ->where('version', $version)
            ->firstOrFail();

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'plugins:'.$plugin->community_id.':'.$plugin->slug.':versions:'.$version.':documents',
                fn (): array => DocumentResource::collection(
                    Document::query()
                        ->with('pluginVersion')
                        ->where('plugin_version_id', $pluginVersion->id)
                        ->orderBy('sort_order')
                        ->orderBy('path')
                        ->get(),
                )->resolve($request),
            ),
        ]);
    }

    public function document(Request $request, Document $document): JsonResponse
    {
        $user = $this->currentUser($request);
        $document = $this->access->documents($user)
            ->with('pluginVersion.plugin.community')
            ->whereKey($document->id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'documents:'.$document->id,
                fn (): array => (new DocumentResource($document))->resolve($request),
            ),
        ]);
    }

    public function command(Request $request, string $slug): JsonResponse
    {
        $user = $this->currentUser($request);
        $command = $this->access->commands($user)
            ->with(['category', 'pluginVersion.plugin.community'])
            ->withCount('views')
            ->where('slug', $slug)
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'data' => $this->cache->remember(
                $user,
                'commands:'.$command->plugin_version_id.':'.$command->slug,
                fn (): array => (new CommandResource($command))->resolve($request),
            ),
        ]);
    }

    /**
     * @return Builder<Plugin>
     */
    private function pluginBySlug(Request $request, ?User $user, string $slug): Builder
    {
        $community = $request->query('community');

        return $this->access->plugins($user)
            ->where('slug', $slug)
            ->when(is_string($community) && $community !== '', fn (Builder $query): Builder => $query
                ->whereHas('community', fn (Builder $communityQuery): Builder => $communityQuery->where('slug', $community)));
    }

    private function currentUser(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $tokenable = $accessToken?->tokenable;

        if ($tokenable instanceof User) {
            return $tokenable;
        }

        // Do not widen an authenticated-looking request to the anonymous public scope.
        return $request->headers->has('Authorization') ? new User : null;
    }
}
