<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Command;
use App\Models\Document;
use App\Models\PluginVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Command>
 */
class CommandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'plugin_version_id' => PluginVersion::factory(),
            'document_id' => Document::factory(),
            'category_id' => Category::factory(),
            'name' => Str::headline($name),
            'slug' => $slug,
            'syntax' => '!'.$slug.' <target>',
            'description' => fake()->sentence(12),
            'aliases' => ['!'.Str::before($slug, '-')],
            'parameters' => [
                'target' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ];
    }
}
