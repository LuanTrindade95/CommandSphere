<?php

namespace Database\Factories;

use App\Models\IngestionRun;
use App\Models\PluginVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestionRun>
 */
class IngestionRunFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(20, 120));

        return [
            'plugin_version_id' => PluginVersion::factory(),
            'status' => fake()->randomElement(['completed', 'failed']),
            'stats' => [
                'documents_seen' => fake()->numberBetween(4, 16),
                'commands_detected' => fake()->numberBetween(8, 35),
                'commands_changed' => fake()->numberBetween(1, 8),
            ],
            'log' => [
                ['level' => 'info', 'message' => 'Repository cloned'],
                ['level' => 'info', 'message' => 'Markdown files scanned'],
            ],
            'started_at' => $startedAt,
            'finished_at' => $startedAt->clone()->addMinutes(fake()->numberBetween(2, 12)),
        ];
    }
}
