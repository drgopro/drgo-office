<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->optional()->paragraph(),
            'priority' => $this->faker->randomElement(['high', 'medium', 'low']),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+2 weeks'),
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
