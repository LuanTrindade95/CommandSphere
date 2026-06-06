<?php

namespace App\Events;

use App\Models\IngestionRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IngestionRunStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly IngestionRun $run,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        $communitySlug = $this->run
            ->loadMissing('pluginVersion.plugin.community')
            ->pluginVersion
            ?->plugin
            ?->community
            ?->slug;

        return new PrivateChannel('community.'.($communitySlug ?? 'unknown'));
    }

    public function broadcastAs(): string
    {
        return 'ingestion.run.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $run = $this->run->loadMissing('pluginVersion.plugin.community');
        $plugin = $run->pluginVersion?->plugin;
        $community = $plugin?->community;

        return [
            'run' => [
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
            ],
        ];
    }
}
