<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandResource;
use App\Models\Command;
use App\Models\CommandView;
use App\Models\Community;
use App\Models\User;
use App\Services\Discovery\DiscoveryAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function view(Request $request, DiscoveryAccess $access, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $command = $access->commands($user)
            ->where('slug', $slug)
            ->latest('id')
            ->firstOrFail();

        $windowStartedAt = now()->subMinutes((int) config('commandsphere.analytics.view_dedupe_minutes'));
        $exists = CommandView::query()
            ->where('command_id', $command->id)
            ->where('user_id', $user->id)
            ->where('viewed_at', '>=', $windowStartedAt)
            ->exists();

        if (! $exists) {
            CommandView::query()->create([
                'command_id' => $command->id,
                'user_id' => $user->id,
                'viewed_at' => now(),
            ]);

            Command::query()->whereKey($command->id)->searchable();
        }

        return response()->json([
            'data' => [
                'command_id' => $command->id,
                'recorded' => ! $exists,
            ],
        ], $exists ? 200 : 201);
    }

    public function mostViewed(Request $request, DiscoveryAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $maxDays = (int) config('commandsphere.analytics.max_period_days', 365);
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:'.$maxDays],
        ]);
        $days = (int) ($validated['days'] ?? 30);
        $communityIds = $this->analyticsCommunityIds($request, $access, $user);
        $periodStartedAt = now()->subDays($days);

        $rows = CommandView::query()
            ->select('command_id', DB::raw('count(*) as views_count'))
            ->where('viewed_at', '>=', $periodStartedAt)
            ->whereHas('command.pluginVersion.plugin', fn (Builder $query): Builder => $query->whereIn('community_id', $communityIds))
            ->groupBy('command_id')
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        $ids = $rows->pluck('command_id')->all();
        $viewCounts = $rows->pluck('views_count', 'command_id');
        $commands = Command::query()
            ->with(['category', 'pluginVersion.plugin.community'])
            ->whereIn('id', $ids)
            ->get()
            ->each(fn (Command $command): Command => $command->setAttribute('views_count', (int) $viewCounts[$command->id]))
            ->sortBy(fn (Command $command): int => array_search($command->id, $ids, true))
            ->values();

        return response()->json([
            'data' => CommandResource::collection($commands)->resolve($request),
            'meta' => [
                'days' => $days,
                'max_days' => $maxDays,
                'period_started_at' => $periodStartedAt->toISOString(),
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function analyticsCommunityIds(Request $request, DiscoveryAccess $access, User $user): array
    {
        $community = $request->query('community');

        if (is_string($community) && $community !== '') {
            $model = $access->communities($user)->where('slug', $community)->firstOrFail();

            if (! $user->hasCommunityPermission($model, 'analytics.view')) {
                throw new AuthorizationException('You do not have permission to view analytics for this community.');
            }

            return [$model->id];
        }

        $ids = $access->communities($user)
            ->get()
            ->filter(fn (Community $model): bool => $user->hasCommunityPermission($model, 'analytics.view'))
            ->pluck('id')
            ->values()
            ->all();

        if ($ids === []) {
            throw new AuthorizationException('You do not have permission to view analytics.');
        }

        return $ids;
    }
}
