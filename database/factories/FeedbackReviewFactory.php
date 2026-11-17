<?php

namespace Database\Factories;

use App\Models\FeedbackPost;
use App\Models\FeedbackReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackReview>
 */
class FeedbackReviewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'feedback_post_id' => fn () => FeedbackPost::create([
                'type' => 'bug', 'title' => $this->faker->sentence(3), 'page' => '캘린더',
                'created_by' => User::factory()->create()->id,
            ])->id,
            'category' => 'bug',
            'effort' => 'small',
            'summary' => $this->faker->sentence(),
            'proposal' => $this->faker->sentence(),
            'reviewed_at' => now(),
            'decision' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['decision' => 'approved', 'decided_at' => now()]);
    }
}
