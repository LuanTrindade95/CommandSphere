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

        $runs = IngestionRun::query()
            ->with('pluginVersion.plugin.community')
            ->latest('id')
            ->get()
            ->filter(fn (IngestionRun $run): bool => $this->canView($user, $run))
            ->values()
            ->map(fn (IngestionRun $run): array => $this->payload($run))
            ->all();

        return response()->json([
            'data' => $runs,
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
     * @return array<string, mixed>
     */
    private function payload(IngestionRun $run): array
    {
        $plugin = $run->pluginVersion?->plugin;
        $community = $plugin?->community;

        return [
            'id' => $run->id,
            'plugin_version_id' => $run->plugin_version_id,
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
