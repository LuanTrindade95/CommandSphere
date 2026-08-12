<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plugin_version_id' => $this->plugin_version_id,
            'plugin_version' => new PluginVersionResource($this->whenLoaded('pluginVersion')),
            'path' => $this->path,
            'title' => $this->title,
            'frontmatter' => $this->frontmatter ?? [],
            'content_html' => $this->content_html,
            'sort_order' => $this->sort_order,
        ];
    }
}
