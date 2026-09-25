<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Word;
use App\Support\LessonReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = Course::factory()->published()->create();

    $this->words = collect([
        ['你好', 'xin chào'], ['谢谢', 'cảm ơn'], ['再见', 'tạm biệt'], ['老师', 'giáo viên'], ['学生', 'học sinh'],
    ])->map(fn ($word) => Word::factory()->create(['hanzi' => $word[0], 'meanings' => $word[1]]));

    $this->lesson = Lesson::factory()->published()->free()->for($this->course)
        ->hasAttached($this->words, ['sort_order' => 1])
        ->create(['sort_order' => 1]);
});

test('lesson page links to part 1 of the review', function () {
    $this->get(route('lessons.show', [$this->course, $this->lesson]))
        ->assertSee('Ôn tập bài học')
        ->assertSee(route('lessons.review', [$this->course, $this->lesson, 1]));
});

test('review without a part redirects to part 1', function () {
    $this->get("/courses/{$this->course->slug}/lessons/{$this->lesson->slug}/review")
        ->assertRedirect(route('lessons.review', [$this->course, $this->lesson, 1]));
});

test('each part has its own page', function (int $part, string $title) {
    $response = $this->get(route('lessons.review', [$this->course, $this->lesson, $part]))
        ->assertOk()
        ->assertSee("Phần {$part}: {$title}")
        ->assertDontSee('Phần 6');

    expect($response->viewData('questions'))->toHaveCount(5);
})->with([
    [1, 'Chọn nghĩa đúng'],
    [2, 'Chọn từ đúng'],
    [3, 'Nhận diện chữ Hán'],
    [4, 'Viết từ'],
    [5, 'Luyện phát âm'],
]);

test('parts that do not exist return 404', function (string $part) {
    $this->get("/courses/{$this->course->slug}/lessons/{$this->lesson->slug}/review/{$part}")->assertNotFound();
})->with(['6', '0', '7', 'abc']);

test('part 1 asks for the meaning, part 2 for the word', function (int $part, string $field) {
    $questions = (new LessonReview($this->lesson->load('words')))->questions($part);

    foreach ($questions as $question) {
        $word = $this->words->firstWhere('id', $question['id']);
        $values = array_column($question['options'], 'value');

        expect($question['answer'])->toBe($word->{$field})
            ->and($values)->toHaveCount(4)
            ->and($values)->toContain($word->{$field})
            ->and(array_unique($values))->toHaveCount(4)
            ->and($values)->each->toBeIn($this->words->pluck($field)->all());
    }
})->with([
    [1, 'meaning_text'],
    [2, 'hanzi'],
    [3, 'meaning_text'],
]);

test('part 3 shows only the hanzi, without pinyin or audio', function () {
    $this->get(route('lessons.review', [$this->course, $this->lesson, 3]))
        ->assertSee('data-quiz="hanzi"', escape: false)
        ->assertDontSee('data-quiz="pinyin"', escape: false)
        ->assertDontSee('data-quiz="speak"', escape: false)
        ->assertDontSee('data-quiz="meaning"', escape: false);

    $this->get(route('lessons.review', [$this->course, $this->lesson, 1]))
        ->assertSee('data-quiz="pinyin"', escape: false)
        ->assertSee('data-quiz="speak"', escape: false);
});

test('part 4 shows the meaning and a text box instead of options', function () {
    $response = $this->get(route('lessons.review', [$this->course, $this->lesson, 4]))
        ->assertSee('data-quiz="meaning"', escape: false)
        ->assertSee('data-quiz="typed-input"', escape: false)
        ->assertDontSee('data-option="0"', escape: false);

    expect(collect($response->viewData('questions'))->pluck('options')->flatten())->toBeEmpty();
});

test('part 2 shows the pinyin with the correct answer', function () {
    $question = collect((new LessonReview($this->lesson->load('words')))->questions(2))->firstWhere('hanzi', '你好');

    expect($question['answer_label'])->toBe('你好 ('.$this->words[0]->pinyin.')');
});

test('short lessons borrow wrong options from the rest of the course', function () {
    $short = Lesson::factory()->published()->free()->for($this->course)
        ->hasAttached(Word::factory()->create(['meanings' => 'mèo']), ['sort_order' => 1])
        ->create(['sort_order' => 2]);

    $values = array_column((new LessonReview($short->load('words')))->questions(1)[0]['options'], 'value');

    expect($values)->toHaveCount(4)
        ->toContain('mèo')
        ->and(array_diff($values, ['mèo']))->each->toBeIn($this->words->pluck('meaning_text')->all());
});

test('part 2 options show the pinyin under each word; other parts have no hint', function () {
    $lesson = $this->lesson->load('words');

    foreach ((new LessonReview($lesson))->questions(2) as $question) {
        foreach ($question['options'] as $option) {
            expect($option['hint'])->toBe($this->words->firstWhere('hanzi', $option['value'])->pinyin);
        }
    }

    foreach ([1, 3] as $part) {
        expect(collect((new LessonReview($lesson))->questions($part))->pluck('options')->flatten(1)->pluck('hint')->filter())->toBeEmpty();
    }
});

test('lessons without words show an empty state', function () {
    $empty = Lesson::factory()->published()->free()->for($this->course)->create(['sort_order' => 3]);

    $this->get(route('lessons.review', [$this->course, $empty, 1]))
        ->assertOk()
        ->assertSee('Bài học này chưa có từ vựng để ôn tập.');
});

test('result offers retry and the next part', function () {
    $this->get(route('lessons.review', [$this->course, $this->lesson, 1]))
        ->assertSee('Làm lại')
        ->assertSee(route('lessons.review', [$this->course, $this->lesson, 2]))
        ->assertSee('Phần tiếp theo →');

    $this->get(route('lessons.review', [$this->course, $this->lesson, 4]))
        ->assertSee(route('lessons.review', [$this->course, $this->lesson, 5]));

    // Part 5 is the last one: its button finishes the review and goes back to the lesson.
    $this->get(route('lessons.review', [$this->course, $this->lesson, 5]))
        ->assertSee('Hoàn thành')
        ->assertDontSee('Phần tiếp theo');
});

test('part 5 shows the meaning with the record button in the same box, and asks guests to log in', function () {
    $this->get(route('lessons.review', [$this->course, $this->lesson, 5]))
        ->assertSee('data-quiz="meaning"', escape: false)
        ->assertDontSee('data-quiz="record"', escape: false)
        ->assertSee('để thu âm và chấm điểm phát âm');

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.review', [$this->course, $this->lesson, 5]))
        ->assertSeeInOrder(['data-quiz="meaning"', 'data-quiz="record"', 'data-quiz="record-status"', 'data-quiz="speech-result"'], escape: false)
        ->assertDontSee('data-quiz="skip"', escape: false)
        ->assertDontSee('data-option="0"', escape: false);
});

test('review follows the lesson access rules', function () {
    $paid = Lesson::factory()->published()->for($this->course)->create(['sort_order' => 4]);
    $draft = Lesson::factory()->free()->for($this->course)->create(['sort_order' => 5]);

    $this->get(route('lessons.review', [$this->course, $paid, 1]))->assertRedirect(route('login'));
    $this->get(route('lessons.review', [$this->course, $draft, 1]))->assertNotFound();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->get(route('lessons.review', [$this->course, $paid, 1]))
        ->assertRedirect(route('courses.show', $this->course));

    $user->enrolledCourses()->attach($this->course, ['enrolled_at' => now()]);
    $this->actingAs($user)->get(route('lessons.review', [$this->course, $paid, 1]))->assertOk();
});
