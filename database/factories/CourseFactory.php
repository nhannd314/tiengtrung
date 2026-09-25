<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(3), '.');

        return [
            'title' => $title,
            'description' => fake()->paragraph(),
            'thumbnail' => null,
            'hsk_level' => fake()->numberBetween(1, 6),
            'sort_order' => 0,
            'is_published' => false,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the course is visible to learners.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
