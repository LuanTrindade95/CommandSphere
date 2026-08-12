<?php

namespace App\Models;

use Database\Factories\PluginVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PluginVersion extends Model
{
    /** @use HasFactory<PluginVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'version',
        'git_ref',
        'changelog',
        'published_at',
        'is_latest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_latest' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plugin, $this>
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Command, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }

    /**
     * @return HasMany<IngestionRun, $this>
     */
    public function ingestionRuns(): HasMany
    {
        return $this->hasMany(IngestionRun::class);
    }
}
