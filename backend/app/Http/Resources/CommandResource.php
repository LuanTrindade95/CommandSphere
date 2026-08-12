<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plugin_version_id' => $this->plugin_version_id,
            'document_id' => $this->document_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'syntax' => $this->syntax,
            'description' => $this->description,
            'aliases' => $this->aliases ?? [],
            'parameters' => $this->parameters ?? [],
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'plugin_version' => new PluginVersionResource($this->whenLoaded('pluginVersion')),
            'plugin' => $this->whenLoaded('pluginVersion', fn (): mixed => $this->pluginVersion?->relationLoaded('plugin')
                ? new PluginResource($this->pluginVersion->plugin)
                : null),
            'views' => (int) ($this->views_count ?? 0),
        ];
    }
}
