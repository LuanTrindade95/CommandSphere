<?php

namespace Database\Seeders;

use App\Models\Community;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CommunityPermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'plugins.manage',
        'ingestion.run',
        'analytics.view',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'community-admin' => [
            'plugins.manage',
            'ingestion.run',
            'analytics.view',
        ],
        'maintainer' => [
            'ingestion.run',
            'analytics.view',
        ],
        'member' => [],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Community::query()
            ->orderBy('id')
            ->each(fn (Community $community): bool => $this->seedCommunityRoles($community));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function seedCommunityRoles(Community $community): bool
    {
        $previousCommunityId = getPermissionsTeamId();

        setPermissionsTeamId($community->getKey());

        try {
            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        } finally {
            setPermissionsTeamId($previousCommunityId);
        }

        return true;
    }
}
