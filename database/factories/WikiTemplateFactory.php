<?php

namespace Database\Factories;

use App\Models\WikiTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiTemplate>
 */
class WikiTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' 템플릿',
            'content' => '<h1>'.fake()->sentence(3).'</h1><p>'.fake()->paragraph().'</p>',
        ];
    }
}
