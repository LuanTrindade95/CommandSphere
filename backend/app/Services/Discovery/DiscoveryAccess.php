<?php

namespace App\Services\Discovery;

use App\Models\Command;
use App\Models\Community;
use App\Models\Document;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class DiscoveryAccess
{
    /**
     * @return list<int>
     */
    public function communityIds(?User $user): array
    {
        if ($user === null) {
            return Community::query()
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return $user->communities()
            ->pluck('communities.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function ensureCommunity(?User $user, Community $community): void
    {
        if ($user === null) {
            return;
        }

        if (! in_array($community->id, $this->communityIds($user), true)) {
            throw new AuthorizationException('You do not have access to this community.');
        }
    }

    /**
     * @return Builder<Community>
     */
    public function communities(?User $user): Builder
    {
        return Community::query()
            ->whereIn('id', $this->communityIds($user));
    }

    /**
     * @return Builder<Plugin>
     */
    public function plugins(?User $user): Builder
    {
        return Plugin::query()
            ->whereIn('community_id', $this->communityIds($user));
    }

    /**
     * @return Builder<Document>
     */
    public function documents(?User $user): Builder
    {
        return Document::query()
            ->whereHas('pluginVersion.plugin', fn (Builder $query): Builder => $query
                ->whereIn('community_id', $this->communityIds($user)));
    }

    /**
     * @return Builder<Command>
     */
    public function commands(?User $user): Builder
    {
        return Command::query()
            ->whereHas('pluginVersion.plugin', fn (Builder $query): Builder => $query
                ->whereIn('community_id', $this->communityIds($user)));
    }
}
