<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IngestionRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $communityIds = $this->viewableCommunityIds($user);

        $runs = IngestionRun::query()
            ->with('pluginVersion.plugin.community')
            ->whereHas(
                'pluginVersion.plugin',
                fn ($query) => $query->whereIn('community_id', $communityIds),
            )
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $runs->getCollection()
                ->map(fn (IngestionRun $run): array => $this->payload($run))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function show(Request $request, IngestionRun $ingestionRun): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ingestionRun->loadMissing('pluginVersion.plugin.community');

        if (! $this->canView($user, $ingestionRun)) {
            throw new AuthorizationException('You do not have permission to view this ingestion run.');
        }

        return response()->json([
            'data' => $this->payload($ingestionRun),
        ]);
    }

    private function canView(User $user, IngestionRun $run): bool
    {
        $community = $run->pluginVersion?->plugin?->community;

        return $community !== null && (
            $user->hasCommunityPermission($community, 'analytics.view')
            || $user->hasCommunityPermission($community, 'ingestion.run')
        );
    }

    /**
     * @return list<int>
     */
    private function viewableCommunityIds(User $user): array
    {
        return $user->communities()
            ->select('communities.id')
            ->get()
            ->filter(fn ($community): bool => $user->hasCommunityPermission($community, 'analytics.view')
                || $user->hasCommunityPermission($community, 'ingestion.run'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(IngestionRun $run): array
    {
        $plugin = $run->pluginVersion?->plugin;
        $community = $plugin?->community;

        return [
            'id' => $run->id,
            'plugin_version_id' => $run->plugin_version_id,
            'source' => $run->source,
            'status' => $run->status,
            'stats' => $run->stats,
            'log' => $run->log,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
            'plugin' => $plugin === null ? null : [
                'id' => $plugin->id,
                'name' => $plugin->name,
                'slug' => $plugin->slug,
            ],
            'community' => $community === null ? null : [
                'id' => $community->id,
                'name' => $community->name,
                'slug' => $community->slug,
            ],
        ];
    }
}
