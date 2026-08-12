<?php

namespace App\Services\Auth;

use App\Models\Community;
use App\Models\User;
use Database\Seeders\CommunityPermissionSeeder;
use Spatie\Permission\Models\Role;

class CommunityMembershipService
{
    public function attachToDefaultCommunityAsMember(User $user): ?Community
    {
        $community = Community::query()
            ->where('slug', config('commandsphere.default_community_slug'))
            ->first()
            ?? Community::query()->orderBy('id')->first();

        if ($community === null) {
            return null;
        }

        $this->attachWithRole($user, $community, 'member');

        return $community;
    }

    public function attachWithRole(User $user, Community $community, string $roleName): void
    {
        $community->users()->syncWithoutDetaching([
            $user->id => ['role' => $roleName],
        ]);

        $previousCommunityId = getPermissionsTeamId();

        setPermissionsTeamId($community->getKey());

        try {
            $role = Role::query()
                ->where('community_id', $community->getKey())
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                app(CommunityPermissionSeeder::class)->seedCommunityRoles($community);

                $role = Role::query()
                    ->where('community_id', $community->getKey())
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->firstOrFail();
            }

            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousCommunityId);
        }
    }
}
