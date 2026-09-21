<?php

namespace App\Models;

use Database\Factories\IngestionRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestionRun extends Model
{
    /** @use HasFactory<IngestionRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_version_id',
        'source',
        'correlation_id',
        'status',
        'stats',
        'log',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PluginVersion, $this>
     */
    public function pluginVersion(): BelongsTo
    {
        return $this->belongsTo(PluginVersion::class);
    }
}
