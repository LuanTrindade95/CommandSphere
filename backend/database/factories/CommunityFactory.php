<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Community';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'discord_guild_id' => fake()->unique()->numerify('8###############'),
            'description' => fake()->paragraph(),
            'branding' => [
                'primary' => '#7C3AED',
                'accent' => '#22D3EE',
                'surface' => '#080B14',
            ],
        ];
    }
}
