<?php

use App\Models\Community;
use App\Models\User;
use Database\Seeders\CommunityPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('checks permissions inside a community scope', function (): void {
    $community = Community::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $this->seed(CommunityPermissionSeeder::class);

    $community->users()->attach($admin, ['role' => 'community-admin']);
    $community->users()->attach($member, ['role' => 'member']);

    $previousCommunityId = getPermissionsTeamId();

    setPermissionsTeamId($community->id);

    try {
        $admin->assignRole(Role::query()
            ->where('community_id', $community->id)
            ->where('name', 'community-admin')
            ->where('guard_name', 'web')
            ->firstOrFail());

        $member->assignRole(Role::query()
            ->where('community_id', $community->id)
            ->where('name', 'member')
            ->where('guard_name', 'web')
            ->firstOrFail());
    } finally {
        setPermissionsTeamId($previousCommunityId);
    }

    expect($member->hasCommunityPermission($community, 'plugins.manage'))->toBeFalse()
        ->and($admin->hasCommunityPermission($community, 'plugins.manage'))->toBeTrue();
});
