<?php

namespace Database\Factories;

use App\Models\Command;
use App\Models\CommandView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandView>
 */
class CommandViewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'command_id' => Command::factory(),
            'user_id' => User::factory(),
            'viewed_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
