<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Command;
use App\Models\CommandView;
use App\Models\Community;
use App\Models\Document;
use App\Models\Favorite;
use App\Models\IngestionRun;
use App\Models\Plugin;
use App\Models\PluginVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = $this->seedUsers();
        $communities = $this->seedCommunities();

        $this->call(CommunityPermissionSeeder::class);
        $this->assignCommunityMembers($communities, $users);

        $categories = $this->seedCategories();
        $versions = $this->seedPluginsAndVersions($communities);
        $documents = $this->seedDocuments($versions);
        $commands = Command::withoutSyncingToSearch(fn (): array => $this->seedCommands($versions, $documents, $categories));

        $this->seedEngagement($users, $commands, $documents);
        $this->seedIngestionRuns($versions);
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        return [
            'admin' => User::factory()->create([
                'name' => 'Demo Admin',
                'email' => 'admin@demo',
                'discord_id' => '900000000000000001',
                'username' => 'admin.demo',
                'avatar' => 'https://cdn.discordapp.com/avatars/900000000000000001/admin.png',
                'password' => Hash::make('password'),
            ]),
            'maintainer' => User::factory()->create([
                'name' => 'Demo Maintainer',
                'email' => 'maintainer@demo',
                'discord_id' => '900000000000000002',
                'username' => 'maintainer.demo',
                'avatar' => 'https://cdn.discordapp.com/avatars/900000000000000002/maintainer.png',
                'password' => Hash::make('password'),
            ]),
            'member' => User::factory()->create([
                'name' => 'Demo Member',
                'email' => 'member@demo',
                'discord_id' => '900000000000000003',
                'username' => 'member.demo',
                'avatar' => 'https://cdn.discordapp.com/avatars/900000000000000003/member.png',
                'password' => Hash::make('password'),
            ]),
        ];
    }

    /**
     * @return array<string, Community>
     */
    private function seedCommunities(): array
    {
        return [
            'celem' => Community::factory()->create([
                'name' => 'Celem Ecosystem',
                'slug' => 'celem-ecosystem',
                'discord_guild_id' => '800000000000000001',
                'description' => 'V Rising server operators sharing command documentation for ProjectM and BepInEx plugins.',
            ]),
            'forge' => Community::factory()->create([
                'name' => 'Forge Operations',
                'slug' => 'forge-operations',
                'discord_guild_id' => '800000000000000002',
                'description' => 'Operations team maintaining production Discord and game-server command references.',
            ]),
        ];
    }

    /**
     * @param  array<string, Community>  $communities
     * @param  array<string, User>  $users
     */
    private function assignCommunityMembers(array $communities, array $users): void
    {
        foreach ($communities as $community) {
            $community->users()->attach($users['admin'], ['role' => 'community-admin']);
            $community->users()->attach($users['maintainer'], ['role' => 'maintainer']);
            $community->users()->attach($users['member'], ['role' => 'member']);

            $this->assignScopedRole($users['admin'], $community, 'community-admin');
            $this->assignScopedRole($users['maintainer'], $community, 'maintainer');
            $this->assignScopedRole($users['member'], $community, 'member');
        }
    }

    private function assignScopedRole(User $user, Community $community, string $roleName): void
    {
        $previousCommunityId = getPermissionsTeamId();

        setPermissionsTeamId($community->getKey());

        try {
            $role = Role::query()
                ->where('community_id', $community->getKey())
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->firstOrFail();

            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousCommunityId);
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $names = [
            'Administration',
            'Moderation',
            'Teleportation',
            'Economy',
            'Combat',
            'Diagnostics',
            'Automation',
        ];

        return collect($names)
            ->mapWithKeys(fn (string $name): array => [
                Str::slug($name) => Category::factory()->create([
                    'name' => $name,
                    'slug' => Str::slug($name),
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, Community>  $communities
     * @return list<PluginVersion>
     */
    private function seedPluginsAndVersions(array $communities): array
    {
        $plugins = [
            ['community' => 'celem', 'name' => 'Celem Core', 'slug' => 'celem-core'],
            ['community' => 'celem', 'name' => 'Blood Economy', 'slug' => 'blood-economy'],
            ['community' => 'celem', 'name' => 'Castle Operations', 'slug' => 'castle-operations'],
            ['community' => 'forge', 'name' => 'Shard Watch', 'slug' => 'shard-watch'],
            ['community' => 'forge', 'name' => 'Raid Scheduler', 'slug' => 'raid-scheduler'],
        ];

        $versions = [];

        foreach ($plugins as $pluginIndex => $pluginData) {
            $plugin = Plugin::factory()->create([
                'community_id' => $communities[$pluginData['community']]->id,
                'name' => $pluginData['name'],
                'slug' => $pluginData['slug'],
                'description' => 'Command documentation surface for '.$pluginData['name'].'.',
                'repository_url' => 'https://github.com/commandsphere/'.$pluginData['slug'],
            ]);

            $versionCount = $pluginIndex % 2 === 0 ? 3 : 2;

            for ($versionIndex = 1; $versionIndex <= $versionCount; $versionIndex++) {
                $version = PluginVersion::factory()->create([
                    'plugin_id' => $plugin->id,
                    'version' => '1.'.$versionIndex.'.'.$pluginIndex,
                    'git_ref' => 'v1.'.$versionIndex.'.'.$pluginIndex,
                    'is_latest' => $versionIndex === $versionCount,
                    'published_at' => Carbon::now()->subWeeks(($versionCount - $versionIndex) + 1),
                ]);

                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * @param  list<PluginVersion>  $versions
     * @return list<Document>
     */
    private function seedDocuments(array $versions): array
    {
        $documents = [];

        foreach ($versions as $index => $version) {
            foreach (['commands', 'admin'] as $sectionIndex => $section) {
                $documents[] = Document::factory()->create([
                    'plugin_version_id' => $version->id,
                    'path' => 'docs/'.$section.'.md',
                    'title' => Str::headline($section).' Reference',
                    'sort_order' => ($index * 2) + $sectionIndex + 1,
                ]);
            }
        }

        return $documents;
    }

    /**
     * @param  list<PluginVersion>  $versions
     * @param  list<Document>  $documents
     * @param  array<string, Category>  $categories
     * @return list<Command>
     */
    private function seedCommands(array $versions, array $documents, array $categories): array
    {
        $commandNames = [
            'ban-player',
            'kick-player',
            'teleport-to-base',
            'grant-currency',
            'inspect-balance',
            'spawn-blood-essence',
            'reload-config',
            'sync-permissions',
            'open-castle-log',
            'lock-region',
            'unlock-region',
            'queue-raid-window',
            'cancel-raid-window',
            'scan-shard-health',
            'list-online-staff',
            'mute-player',
            'unmute-player',
            'audit-player',
            'repair-castle-heart',
            'reset-cooldowns',
        ];
        $categoryKeys = array_keys($categories);
        $commands = [];
        $sequence = 0;

        foreach ($versions as $versionIndex => $version) {
            $versionDocuments = collect($documents)
                ->filter(fn (Document $document): bool => $document->plugin_version_id === $version->id)
                ->values();

            $commandsPerVersion = $versionIndex === 0 ? 4 : 3;

            foreach (array_slice($commandNames, 0, $commandsPerVersion) as $name) {
                $category = $categories[$categoryKeys[$sequence % count($categoryKeys)]];
                $document = $versionDocuments[$sequence % max(1, $versionDocuments->count())] ?? $versionDocuments->first();
                $commandSlug = $name;

                $commands[] = Command::factory()->create([
                    'plugin_version_id' => $version->id,
                    'document_id' => $document?->id,
                    'category_id' => $category->id,
                    'name' => Str::headline($name),
                    'slug' => $commandSlug,
                    'syntax' => '!'.$commandSlug.' '.($sequence % 2 === 0 ? '<player>' : '[reason]'),
                    'description' => 'Operational command for '.Str::headline($name).'.',
                    'aliases' => ['!'.Str::before($commandSlug, '-')],
                    'parameters' => [
                        'player' => [
                            'type' => 'string',
                            'required' => $sequence % 2 === 0,
                        ],
                    ],
                ]);

                $sequence++;
            }
        }

        return $commands;
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<Command>  $commands
     * @param  list<Document>  $documents
     */
    private function seedEngagement(array $users, array $commands, array $documents): void
    {
        foreach (array_slice($commands, 0, 8) as $index => $command) {
            Favorite::factory()->create([
                'user_id' => $users[array_keys($users)[$index % count($users)]]->id,
                'favoritable_type' => Command::class,
                'favoritable_id' => $command->id,
            ]);
        }

        foreach (array_slice($documents, 0, 4) as $index => $document) {
            Favorite::factory()->create([
                'user_id' => $users[array_keys($users)[$index % count($users)]]->id,
                'favoritable_type' => Document::class,
                'favoritable_id' => $document->id,
            ]);
        }

        foreach (array_slice($commands, 0, 30) as $index => $command) {
            CommandView::factory()->create([
                'command_id' => $command->id,
                'user_id' => $users[array_keys($users)[$index % count($users)]]->id,
                'viewed_at' => Carbon::now()->subHours($index + 1),
            ]);
        }
    }

    /**
     * @param  list<PluginVersion>  $versions
     */
    private function seedIngestionRuns(array $versions): void
    {
        IngestionRun::factory()->create([
            'plugin_version_id' => $versions[0]->id,
            'status' => 'completed',
        ]);

        IngestionRun::factory()->create([
            'plugin_version_id' => $versions[1]->id,
            'status' => 'failed',
            'log' => [
                ['level' => 'error', 'message' => 'Markdown frontmatter contained an unsupported value type.'],
            ],
        ]);
    }
}
