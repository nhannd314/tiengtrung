<?php

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('slug is generated from the title when missing', function () {
    $course = Course::factory()->create(['title' => 'Tiếng Trung sơ cấp', 'slug' => null]);

    expect($course->slug)->toBe('tieng-trung-so-cap');
});

test('an explicit slug is kept', function () {
    $course = Course::factory()->create(['title' => 'Tiếng Trung sơ cấp', 'slug' => 'custom-slug']);

    expect($course->slug)->toBe('custom-slug');
});

test('duplicate slugs get a numeric suffix, including soft deleted rows', function () {
    Course::factory()->create(['title' => 'HSK 1', 'slug' => null])->delete();

    $second = Course::factory()->create(['title' => 'HSK 1', 'slug' => null]);
    $third = Course::factory()->create(['title' => 'HSK 1', 'slug' => null]);

    expect($second->slug)->toBe('hsk-1-2')
        ->and($third->slug)->toBe('hsk-1-3');
});

test('lesson slugs are unique per course', function () {
    [$courseA, $courseB] = Course::factory()->count(2)->create();

    $first = Lesson::factory()->for($courseA)->create(['title' => 'Chào hỏi', 'slug' => null]);
    $sameCourse = Lesson::factory()->for($courseA)->create(['title' => 'Chào hỏi', 'slug' => null]);
    $otherCourse = Lesson::factory()->for($courseB)->create(['title' => 'Chào hỏi', 'slug' => null]);

    expect($first->slug)->toBe('chao-hoi')
        ->and($sameCourse->slug)->toBe('chao-hoi-2')
        ->and($otherCourse->slug)->toBe('chao-hoi');
});
