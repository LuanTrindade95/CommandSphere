<?php

namespace Database\Factories;

use App\Models\Plugin;
use App\Models\PluginVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PluginVersion>
 */
class PluginVersionFactory extends Factory
{
    public function definition(): array
    {
        $version = fake()->numberBetween(1, 3).'.'.fake()->numberBetween(0, 9).'.'.fake()->numberBetween(0, 9);

        return [
            'plugin_id' => Plugin::factory(),
            'version' => $version,
            'git_ref' => 'v'.$version,
            'changelog' => fake()->paragraph(),
            'published_at' => fake()->dateTimeBetween('-12 months', 'now'),
            'is_latest' => false,
        ];
    }

    public function latest(): static
    {
        return $this->state(fn (): array => [
            'is_latest' => true,
            'published_at' => now(),
        ]);
    }
}
