<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'community' => new CommunityResource($this->whenLoaded('community')),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'repository_url' => $this->repository_url,
            'documentation_path' => $this->documentation_path,
            'default_branch' => $this->default_branch,
            'versions' => PluginVersionResource::collection($this->whenLoaded('versions')),
        ];
    }
}
