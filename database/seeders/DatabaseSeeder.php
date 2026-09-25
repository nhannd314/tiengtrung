<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstWhere('email', 'admin@example.com') ?? User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $student = User::firstWhere('email', 'test@example.com') ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            HskWordSeeder::class,
            CourseSeeder::class,
        ]);

        $course = Course::where('slug', 'tieng-trung-so-cap-hsk-1')->first();
        $student->enrolledCourses()->syncWithoutDetaching([$course->id => ['enrolled_at' => now()]]);
    }
}
