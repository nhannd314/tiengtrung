<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonReviewResult;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = Course::factory()->published()->create();

    $this->freeLesson = Lesson::factory()->published()->free()->for($this->course)
        ->hasAttached(Word::factory()->create([
            'hanzi' => '你好',
            'pinyin' => 'nǐ hǎo',
            'meanings' => 'xin chào',
            'examples' => [['sentence' => '你好，老师！', 'pinyin' => 'Nǐ hǎo, lǎoshī!', 'meaning' => 'Em chào thầy!', 'audio_url' => null]],
        ]), ['sort_order' => 1])
        ->create(['title' => 'Chào hỏi', 'slug' => 'chao-hoi', 'sort_order' => 1, 'content' => '<h2>Mở đầu</h2>']);

    $this->paidLesson = Lesson::factory()->published()->for($this->course)
        ->create(['title' => 'Gia đình', 'slug' => 'gia-dinh', 'sort_order' => 2]);

    $this->student = User::factory()->create();
});

function enroll(User $user, Course $course): void
{
    $user->enrolledCourses()->attach($course, ['enrolled_at' => now()]);
}

/**
 * Save review results for the given parts, each with $correct of 10 questions right.
 *
 * @param  list<int>  $parts
 */
function reviewLesson(User $user, Lesson $lesson, int $correct = 10, array $parts = [1, 2, 3, 4]): void
{
    foreach ($parts as $part) {
        LessonReviewResult::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'part' => $part,
            'correct_count' => $correct,
            'total_questions' => 10,
            'duration_seconds' => 60,
            'mistakes' => [],
            'completed_at' => now(),
        ]);
    }
}

test('guests can study free lessons', function () {
    $this->get(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertOk()
        ->assertSee('Chào hỏi')
        ->assertSee('<h2>Mở đầu</h2>', escape: false)
        ->assertSee('你好')
        ->assertSee('nǐ hǎo')
        ->assertSeeInOrder(['你好，老师！', 'Nǐ hǎo, lǎoshī!', 'Em chào thầy!'])
        ->assertSee('data-speak="你好"', escape: false)
        ->assertSee('data-speak="你好，老师！"', escape: false);
});

test('each meaning is shown with its own part of speech', function () {
    $this->freeLesson->words()->first()->update(['meanings' => [['adjective', 'tốt'], ['adverb', 'rất'], [null, 'hay']]]);

    $this->get(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertOk()
        ->assertSeeInOrder(['1.', 'Tính từ', 'tốt', '2.', 'Phó từ', 'rất', '3.', 'hay']);
});

test('words without examples render without an examples list', function () {
    $this->freeLesson->words()->first()->update(['examples' => null]);

    $this->get(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertOk()
        ->assertDontSee('Nghe câu ví dụ');
});

test('guests are sent to login for paid lessons', function () {
    $this->get(route('lessons.show', [$this->course, $this->paidLesson]))
        ->assertRedirect(route('login'));
});

test('users who are not enrolled are sent back to the course page', function () {
    $this->actingAs($this->student)
        ->get(route('lessons.show', [$this->course, $this->paidLesson]))
        ->assertRedirect(route('courses.show', $this->course))
        ->assertSessionHas('error');
});

test('enrolled users can study paid lessons and their visit is recorded', function () {
    enroll($this->student, $this->course);

    $this->actingAs($this->student)
        ->get(route('lessons.show', [$this->course, $this->paidLesson]))
        ->assertOk()
        ->assertSee('Gia đình');

    $progress = $this->student->lessonProgress()->whereKey($this->paidLesson->id)->first()->pivot;
    expect($progress->started_at)->not->toBeNull()
        ->and($progress->completed_at)->toBeNull();
});

test('draft lessons return 404 for learners', function () {
    $draft = Lesson::factory()->free()->for($this->course)->create();

    $this->get(route('lessons.show', [$this->course, $draft]))->assertNotFound();
});

test('lessons are scoped to their course', function () {
    $otherCourse = Course::factory()->published()->create();

    $this->get(route('lessons.show', [$otherCourse, $this->freeLesson]))->assertNotFound();
});

test('the complete button only shows after parts 1-4 are reviewed with over 90% correct', function (array $parts, array $correct, bool $visible) {
    foreach ($parts as $index => $part) {
        reviewLesson($this->student, $this->freeLesson, $correct[$index], [$part]);
    }

    $response = $this->actingAs($this->student)->get(route('lessons.show', [$this->course, $this->freeLesson]))->assertOk();

    $visible
        ? $response->assertSee('Hoàn thành bài học')->assertDontSee('với trên 90% câu đúng')
        : $response->assertDontSee('Hoàn thành bài học')->assertSee('với trên 90% câu đúng');
})->with([
    'no review yet' => [[], [], false],
    'part 4 missing' => [[1, 2, 3, 5], [10, 10, 10, 10], false],
    'exactly 90%' => [[1, 2, 3, 4], [9, 9, 9, 9], false],
    'over 90% overall' => [[1, 2, 3, 4], [10, 10, 9, 9], true],
    'all correct' => [[1, 2, 3, 4], [10, 10, 10, 10], true],
]);

test('lessons cannot be completed before the review requirement is met', function () {
    reviewLesson($this->student, $this->freeLesson, 9);

    $this->actingAs($this->student)
        ->post(route('lessons.complete', [$this->course, $this->freeLesson]))
        ->assertRedirect(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertSessionHas('status', 'Ôn tập đủ phần 1, 2, 3, 4 với trên 90% câu đúng để hoàn thành bài học.');

    expect($this->student->lessonProgress()->whereKey($this->freeLesson->id)->first()?->pivot->completed_at)->toBeNull();
});

test('completing a lesson goes to the next lesson', function () {
    enroll($this->student, $this->course);
    reviewLesson($this->student, $this->freeLesson);

    $this->actingAs($this->student)
        ->post(route('lessons.complete', [$this->course, $this->freeLesson]))
        ->assertRedirect(route('lessons.show', [$this->course, $this->paidLesson]));

    expect($this->student->lessonProgress()->whereKey($this->freeLesson->id)->first()->pivot->completed_at)->not->toBeNull();
});

test('completing every lesson completes the course', function () {
    enroll($this->student, $this->course);
    reviewLesson($this->student, $this->freeLesson);
    reviewLesson($this->student, $this->paidLesson);

    $this->actingAs($this->student)->post(route('lessons.complete', [$this->course, $this->freeLesson]));
    expect($this->student->enrolledCourses()->first()->pivot->completed_at)->toBeNull();

    $this->actingAs($this->student)
        ->post(route('lessons.complete', [$this->course, $this->paidLesson]))
        ->assertRedirect(route('courses.show', $this->course));

    expect($this->student->enrolledCourses()->first()->pivot->completed_at)->not->toBeNull();
});

test('users cannot complete lessons they cannot access', function () {
    $this->actingAs($this->student)
        ->post(route('lessons.complete', [$this->course, $this->paidLesson]))
        ->assertForbidden();
});

test('attachments can be downloaded by users with access', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $path = UploadedFile::fake()->create('bai-1.pdf', 100)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $this->paidLesson->update(['attachments' => [['path' => $path, 'name' => 'Bài 1.pdf']]]);

    $url = route('lessons.attachments.download', [$this->course, $this->paidLesson, 0]);

    $this->actingAs($this->student)->get($url)->assertForbidden();

    enroll($this->student, $this->course);

    $this->actingAs($this->student)
        ->get($url)
        ->assertOk()
        ->assertDownload()
        ->assertHeaderContains('content-disposition', "filename*=utf-8''B%C3%A0i%201.pdf");
});

test('attachments stored as plain paths (Filament FileUpload) are listed and downloadable', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $pdf = UploadedFile::fake()->create('a.pdf', 100)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $mp3 = UploadedFile::fake()->create('b.mp3', 300)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $this->freeLesson->update(['attachments' => [$pdf, ['path' => $mp3, 'name' => 'Nghe bài 1.mp3']]]);

    $this->get(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertSee('2 tài liệu')
        ->assertSeeInOrder([basename($pdf), 'Nghe bài 1.mp3'])
        ->assertSee(route('lessons.attachments.download', [$this->course, $this->freeLesson, 1]));

    $this->get(route('lessons.attachments.download', [$this->course, $this->freeLesson, 0]))
        ->assertDownload(basename($pdf));
});

test('audio attachments get a player and keep the download link', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $pdf = UploadedFile::fake()->create('a.pdf', 100)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $mp3 = UploadedFile::fake()->create('b.mp3', 300)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $this->freeLesson->update(['attachments' => [$pdf, ['path' => $mp3, 'name' => 'Nghe bài 1.mp3']]]);

    $response = $this->get(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertSee('<audio', false)
        ->assertSee(route('lessons.attachments.stream', [$this->course, $this->freeLesson, 1]))
        ->assertSee(route('lessons.attachments.download', [$this->course, $this->freeLesson, 1]))
        ->assertDontSee(route('lessons.attachments.stream', [$this->course, $this->freeLesson, 0]));

    expect(substr_count($response->getContent(), '<audio'))->toBe(1);
});

test('audio attachments are streamed inline with range support', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $path = 'lesson-attachments/X/nghe.mp3';
    Storage::disk(Lesson::ATTACHMENTS_DISK)->put($path, str_repeat('a', 1000));
    $this->freeLesson->update(['attachments' => [['path' => $path, 'name' => 'Nghe bài 1.mp3']]]);

    $url = route('lessons.attachments.stream', [$this->course, $this->freeLesson, 0]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'audio/mpeg')
        ->assertHeader('accept-ranges', 'bytes')
        ->assertHeaderContains('content-disposition', 'inline');

    $this->get($url, ['Range' => 'bytes=0-99'])
        ->assertStatus(206)
        ->assertHeader('content-length', '100');
});

test('only audio can be streamed and access rules apply', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $pdf = UploadedFile::fake()->create('a.pdf', 100)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $mp3 = UploadedFile::fake()->create('b.mp3', 300)->store('lesson-attachments', Lesson::ATTACHMENTS_DISK);
    $this->freeLesson->update(['attachments' => [$pdf]]);
    $this->paidLesson->update(['attachments' => [$mp3]]);

    $this->get(route('lessons.attachments.stream', [$this->course, $this->freeLesson, 0]))->assertNotFound();

    $this->actingAs($this->student)
        ->get(route('lessons.attachments.stream', [$this->course, $this->paidLesson, 0]))
        ->assertForbidden();
});

test('unknown or missing attachments return 404', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $this->freeLesson->update(['attachments' => ['lesson-attachments/deleted.pdf']]);

    $this->get(route('lessons.attachments.download', [$this->course, $this->freeLesson, 0]))->assertNotFound();
    $this->get(route('lessons.attachments.download', [$this->course, $this->freeLesson, 5]))->assertNotFound();
});

test('course page links accessible lessons', function () {
    $this->get(route('courses.show', $this->course))
        ->assertSee(route('lessons.show', [$this->course, $this->freeLesson]))
        ->assertDontSee(route('lessons.show', [$this->course, $this->paidLesson]));
});
