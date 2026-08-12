<?php

namespace App\Http\Resources;

use App\Models\Command;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $favoritable = $this->whenLoaded('favoritable');

        return [
            'id' => $this->id,
            'type' => $this->type(),
            'favoritable_id' => $this->favoritable_id,
            'item' => $favoritable instanceof Command
                ? new CommandResource($favoritable)
                : ($favoritable instanceof Document ? new DocumentResource($favoritable) : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function type(): string
    {
        return match ($this->favoritable_type) {
            Command::class => 'command',
            Document::class => 'document',
            default => 'unknown',
        };
    }
}
