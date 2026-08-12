<?php

namespace App\Models;

use Database\Factories\CommandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Command extends Model
{
    /** @use HasFactory<CommandFactory> */
    use HasFactory;

    use Searchable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_version_id',
        'document_id',
        'category_id',
        'name',
        'slug',
        'syntax',
        'description',
        'aliases',
        'parameters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'parameters' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PluginVersion, $this>
     */
    public function pluginVersion(): BelongsTo
    {
        return $this->belongsTo(PluginVersion::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<CommandView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(CommandView::class);
    }

    /**
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing([
            'category',
            'pluginVersion.plugin.community',
        ]);

        $pluginVersion = $this->pluginVersion;
        $plugin = $pluginVersion?->plugin;
        $community = $plugin?->community;

        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'plugin_version_id' => $this->plugin_version_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'syntax' => $this->syntax,
            'description' => $this->description,
            'aliases' => $this->aliases ?? [],
            'category' => $this->category?->slug,
            'category_name' => $this->category?->name,
            'plugin' => $plugin?->slug,
            'plugin_name' => $plugin?->name,
            'plugin_version' => $pluginVersion?->version,
            'community' => $community?->slug,
            'community_name' => $community?->name,
            'views' => (int) ($this->views_count ?? $this->views()->count()),
        ];
    }
}
