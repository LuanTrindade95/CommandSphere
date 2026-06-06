<?php

namespace App\Models;

use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plugin extends Model
{
    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'name',
        'slug',
        'description',
        'repository_url',
        'documentation_path',
        'default_branch',
    ];

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return HasMany<PluginVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PluginVersion::class);
    }

    /**
     * @return HasMany<PluginVersion, $this>
     */
    public function latestVersions(): HasMany
    {
        return $this->versions()->where('is_latest', true);
    }
}
