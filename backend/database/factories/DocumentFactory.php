<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\PluginVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $path = 'docs/'.Str::slug($title).'.md';

        return [
            'plugin_version_id' => PluginVersion::factory(),
            'path' => $path,
            'title' => $title,
            'frontmatter' => [
                'title' => $title,
                'source' => $path,
            ],
            'content_raw' => '# '.$title."\n\n".fake()->paragraph(),
            'content_html' => '<h1>'.$title.'</h1><p>'.fake()->paragraph().'</p>',
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
