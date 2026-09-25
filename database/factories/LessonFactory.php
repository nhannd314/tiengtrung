<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(4), '.');

        return [
            'course_id' => Course::factory(),
            'title' => $title,
            'summary' => fake()->sentence(),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'video_url' => null,
            'duration_minutes' => fake()->numberBetween(10, 45),
            'sort_order' => 0,
            'is_free' => false,
            'is_published' => false,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the lesson is visible to learners.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the lesson can be viewed without enrolling.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }
}
