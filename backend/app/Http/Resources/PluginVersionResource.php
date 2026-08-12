<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluginVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plugin_id' => $this->plugin_id,
            'version' => $this->version,
            'git_ref' => $this->git_ref,
            'changelog' => $this->changelog,
            'published_at' => $this->published_at?->toISOString(),
            'is_latest' => (bool) $this->is_latest,
        ];
    }
}
