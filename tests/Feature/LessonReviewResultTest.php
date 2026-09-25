<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonReviewResult;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = Course::factory()->published()->create();
    $this->words = collect(['xin chào', 'cảm ơn', 'tạm biệt'])
        ->map(fn (string $meaning) => Word::factory()->create(['meanings' => $meaning]));
    $this->lesson = Lesson::factory()->published()->free()->for($this->course)
        ->hasAttached($this->words, ['sort_order' => 1])
        ->create(['sort_order' => 1]);
    $this->user = User::factory()->create();
});

/**
 * Build a part submission; $wrong lists word indexes answered incorrectly.
 */
function partSubmission(Collection $words, string $field = 'meaning_text', array $wrong = [], int $duration = 95): array
{
    return [
        'duration_seconds' => $duration,
        'answers' => $words->map(fn (Word $word, int $i) => [
            'word_id' => $word->id,
            'answer' => in_array($i, $wrong) ? 'sai' : $word->{$field},
        ])->all(),
    ];
}

test('a finished part is graded on the server and saved', function () {
    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 1]), partSubmission($this->words, wrong: [1]))
        ->assertOk()
        ->assertJson(['correct_count' => 2, 'total_questions' => 3, 'duration' => '1:35']);

    $result = LessonReviewResult::sole();
    expect($result->user_id)->toBe($this->user->id)
        ->and($result->lesson_id)->toBe($this->lesson->id)
        ->and($result->part)->toBe(1)
        ->and($result->duration_seconds)->toBe(95)
        ->and($result->mistakes)->toBe([$this->words[1]->id]);
});

test('part 2 is graded by the chosen word', function () {
    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 2]), partSubmission($this->words, 'hanzi', wrong: [0]))
        ->assertOk()
        ->assertJson(['correct_count' => 2]);

    expect(LessonReviewResult::sole()->part)->toBe(2);
});

test('part 4 accepts typed hanzi or toned pinyin', function () {
    $this->words[0]->update(['hanzi' => '你好', 'pinyin' => 'nǐ hǎo']);
    $this->words[1]->update(['hanzi' => '谢谢', 'pinyin' => 'xièxie']);
    $this->words[2]->update(['hanzi' => '再见', 'pinyin' => 'zàijiàn']);

    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 4]), [
            'duration_seconds' => 40,
            'answers' => [
                ['word_id' => $this->words[0]->id, 'answer' => '你好'],
                ['word_id' => $this->words[1]->id, 'answer' => 'xie4xie'],
                ['word_id' => $this->words[2]->id, 'answer' => 'zaijian'], // no tones: wrong
            ],
        ])
        ->assertOk()
        ->assertJson(['correct_count' => 2, 'total_questions' => 3]);

    expect(LessonReviewResult::sole()->mistakes)->toBe([$this->words[2]->id]);
});

test('each part keeps only its latest result', function () {
    $part1 = route('lessons.review.store', [$this->course, $this->lesson, 1]);
    $part2 = route('lessons.review.store', [$this->course, $this->lesson, 2]);

    $this->actingAs($this->user)->postJson($part1, partSubmission($this->words, wrong: [0, 1, 2], duration: 200));
    $this->actingAs($this->user)->postJson($part1, partSubmission($this->words, duration: 60));
    $this->actingAs($this->user)->postJson($part2, partSubmission($this->words, 'hanzi', duration: 30));

    expect(LessonReviewResult::count())->toBe(2);

    $part1Result = LessonReviewResult::firstWhere('part', 1);
    expect($part1Result->correct_count)->toBe(3)
        ->and($part1Result->duration_seconds)->toBe(60);
});

test('unfinished parts are rejected and not saved', function () {
    $partial = partSubmission($this->words);
    array_pop($partial['answers']);

    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 1]), $partial)
        ->assertJsonValidationErrors('answers');

    expect(LessonReviewResult::count())->toBe(0);
});

test('guests cannot save, users need access, and unknown parts are 404', function () {
    $paid = Lesson::factory()->published()->for($this->course)->create(['sort_order' => 2]);

    $this->postJson(route('lessons.review.store', [$this->course, $this->lesson, 1]), partSubmission($this->words))
        ->assertUnauthorized();

    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $paid, 1]), partSubmission($this->words))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 6]), partSubmission($this->words))
        ->assertNotFound();
});

test('sidebar shows stats of finished parts', function () {
    $this->actingAs($this->user)->postJson(
        route('lessons.review.store', [$this->course, $this->lesson, 1]),
        partSubmission($this->words, wrong: [1], duration: 125),
    );

    $this->actingAs($this->user)
        ->get(route('lessons.review', [$this->course, $this->lesson, 2]))
        ->assertSee('Thống kê ôn tập')
        ->assertSee('67%')
        ->assertSeeInOrder(['Số câu đúng', '2/3', 'Tổng thời gian', '2:05', 'Đã làm', '1/5 phần'])
        ->assertSeeInOrder(['Phần 1', '2/3', '2:05', 'Phần 2', 'Chưa làm', 'Phần 3', 'Chưa làm', 'Phần 4', 'Chưa làm', 'Phần 5', 'Chưa làm'])
        ->assertDontSee('Sắp ra mắt')
        ->assertSeeInOrder(['Từ cần xem lại (1)', $this->words[1]->hanzi, 'cảm ơn']);
});

test('sidebar asks guests to log in and new users to finish a part', function () {
    $this->get(route('lessons.review', [$this->course, $this->lesson, 1]))
        ->assertSee('để lưu và xem thống kê kết quả ôn tập');

    $this->actingAs($this->user)
        ->get(route('lessons.review', [$this->course, $this->lesson, 1]))
        ->assertSee('Bạn chưa hoàn thành phần ôn tập nào.');
});

test('saving returns the refreshed sidebar', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('lessons.review.store', [$this->course, $this->lesson, 1]),
        partSubmission($this->words),
    );

    expect($response->json('stats_html'))
        ->toContain('100%')
        ->toContain('Không sai từ nào');
});

test('lesson page does not show review results', function () {
    $this->actingAs($this->user)->postJson(
        route('lessons.review.store', [$this->course, $this->lesson, 1]),
        partSubmission($this->words, duration: 125),
    );

    $this->actingAs($this->user)
        ->get(route('lessons.show', [$this->course, $this->lesson]))
        ->assertSee('Ôn tập bài học')
        ->assertDontSee('2:05');
});
