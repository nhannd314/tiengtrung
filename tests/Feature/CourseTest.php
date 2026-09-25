<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = Course::factory()->published()->create(['title' => 'HSK 1 cơ bản']);

    Lesson::factory()->published()->free()
        ->for($this->course)
        ->hasAttached(Word::factory()->create(['hanzi' => '你好', 'pinyin' => 'nǐ hǎo']), ['sort_order' => 1])
        ->create(['title' => 'Chào hỏi', 'sort_order' => 1]);
    Lesson::factory()->published()->for($this->course)->create(['title' => 'Gia đình', 'sort_order' => 2]);
    Lesson::factory()->for($this->course)->create(['title' => 'Bài nháp', 'sort_order' => 3]);
});

test('course page shows published lessons and vocabulary', function () {
    $this->get(route('courses.show', $this->course))
        ->assertOk()
        ->assertSeeInOrder(['HSK 1 cơ bản', 'Chào hỏi', 'Gia đình'])
        ->assertDontSee('Bài nháp')
        ->assertSee('你好')
        ->assertSee('Đăng ký khoá học');
});

test('home page links to the course page', function () {
    $this->get(route('home'))->assertSee(route('courses.show', $this->course));
});

test('unpublished courses are hidden from learners but visible to admins', function () {
    $draft = Course::factory()->create();

    $this->get(route('courses.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('courses.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get(route('courses.show', $draft))->assertOk();
});

test('guests must log in to enroll', function () {
    $this->post(route('courses.enroll', $this->course))->assertRedirect(route('login'));
});

test('users can enroll and then see their progress', function () {
    $user = User::factory()->create();
    $firstLesson = $this->course->lessons()->first();
    $user->lessonProgress()->attach($firstLesson, ['completed_at' => now()]);

    $this->actingAs($user)
        ->post(route('courses.enroll', $this->course))
        ->assertRedirect(route('courses.show', $this->course));

    expect($user->enrolledCourses()->whereKey($this->course->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('courses.show', $this->course))
        ->assertSee('Tiến độ của bạn')
        ->assertSee('1/2 bài')
        ->assertSee('Tiếp tục học')
        ->assertDontSee('Đăng ký khoá học');
});

test('enrolling twice does not duplicate the enrollment', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('courses.enroll', $this->course));
    $this->actingAs($user)->post(route('courses.enroll', $this->course));

    expect($user->enrolledCourses()->count())->toBe(1);
});

test('users cannot enroll in unpublished courses', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('courses.enroll', Course::factory()->create()))
        ->assertNotFound();
});
