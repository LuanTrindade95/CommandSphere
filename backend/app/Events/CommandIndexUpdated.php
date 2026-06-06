<?php

namespace App\Events;

use App\Models\Community;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandIndexUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Community $community,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('community.'.$this->community->slug);
    }

    public function broadcastAs(): string
    {
        return 'commands.index.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'community' => [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
            ],
        ];
    }
}
