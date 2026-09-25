<?php

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('creating a course without a slug generates one and records the creator', function () {
    Livewire::test(CreateCourse::class)
        ->fillForm([
            'title' => 'Tiếng Trung giao tiếp',
            'hsk_level' => 2,
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $course = Course::sole();

    expect($course->slug)->toBe('tieng-trung-giao-tiep')
        ->and($course->created_by)->toBe($this->admin->id)
        ->and($course->hsk_level)->toBe(2);
});

test('publishing a course fills in the publish date', function () {
    Livewire::test(CreateCourse::class)
        ->fillForm(['title' => 'HSK 3'])
        ->set('data.is_published', true)
        ->assertNotSet('data.published_at', null);
});

test('slug must be unique and well formed', function () {
    Course::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateCourse::class)
        ->fillForm(['title' => 'A', 'slug' => 'taken'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(CreateCourse::class)
        ->fillForm(['title' => 'A', 'slug' => 'Not A Slug'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'regex']);
});

test('slug is required when editing', function () {
    $course = Course::factory()->create();

    Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
        ->fillForm(['slug' => null])
        ->call('save')
        ->assertHasFormErrors(['slug' => 'required']);
});
