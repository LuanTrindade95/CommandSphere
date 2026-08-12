<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'community_id' => Community::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'repository_url' => 'https://github.com/example/'.Str::slug($name),
            'documentation_path' => 'docs',
            'default_branch' => 'main',
        ];
    }
}
