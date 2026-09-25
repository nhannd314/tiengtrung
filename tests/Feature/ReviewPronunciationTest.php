<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonReviewResult;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->course = Course::factory()->published()->create();
    $this->words = collect([['你好', 'xin chào'], ['谢谢', 'cảm ơn']])
        ->map(fn ($word) => Word::factory()->create(['hanzi' => $word[0], 'meanings' => $word[1]]));
    $this->lesson = Lesson::factory()->published()->free()->for($this->course)
        ->hasAttached($this->words, ['sort_order' => 1])
        ->create(['sort_order' => 1]);
    $this->user = User::factory()->create();
    $this->url = route('lessons.review.pronunciation', [$this->course, $this->lesson]);

    // Configured after the words are created, so they don't get text-to-speech audio (WordObserver).
    config([
        'services.azure_speech.key' => 'test-key',
        'services.azure_speech.region' => 'southeastasia',
        'services.azure_speech.language' => 'zh-CN',
    ]);
});

function fakeAzure(float $score, string $recognized = '你好'): void
{
    Http::fake([
        '*.stt.speech.microsoft.com/*' => Http::response([
            'RecognitionStatus' => 'Success',
            'DisplayText' => $recognized,
            'NBest' => [[
                'Lexical' => $recognized,
                'Display' => $recognized,
                'AccuracyScore' => $score,
                'FluencyScore' => 90,
                'CompletenessScore' => 100,
                'PronScore' => $score,
            ]],
        ]),
    ]);
}

function wavUpload(): UploadedFile
{
    return UploadedFile::fake()->create('answer.wav', 20, 'audio/wav');
}

test('a recording is scored by Azure against the word', function () {
    fakeAzure(85.4);

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['score' => 85, 'accuracy' => 85, 'fluency' => 90, 'completeness' => 100, 'recognized' => '你好', 'passed' => true]);

    Http::assertSent(function (Request $request) {
        $assessment = json_decode(base64_decode($request->header('Pronunciation-Assessment')[0]), true);

        return str_starts_with($request->url(), 'https://southeastasia.stt.speech.microsoft.com/')
            && str_contains($request->url(), 'language=zh-CN')
            && $request->header('Ocp-Apim-Subscription-Key')[0] === 'test-key'
            && $assessment['ReferenceText'] === '你好';
    });
});

test('scores below the pass mark do not pass', function () {
    fakeAzure(42);

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertJson(['score' => 42, 'passed' => false]);
});

test('silence scores zero', function () {
    Http::fake(['*' => Http::response(['RecognitionStatus' => 'InitialSilenceTimeout'])]);

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertJson(['score' => 0, 'recognized' => '', 'passed' => false]);
});

test('part 5 is graded with the scores the server got from Azure, not what the browser sends', function () {
    fakeAzure(80);
    $this->actingAs($this->user)->get(route('lessons.review', [$this->course, $this->lesson, 5]));
    $this->actingAs($this->user)->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json']);

    // Word 2 was skipped (never recorded) even though the browser claims an answer.
    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 5]), [
            'duration_seconds' => 30,
            'answers' => [
                ['word_id' => $this->words[0]->id, 'answer' => '你好'],
                ['word_id' => $this->words[1]->id, 'answer' => '谢谢'],
            ],
        ])
        ->assertOk()
        ->assertJson(['correct_count' => 1, 'total_questions' => 2]);

    expect(LessonReviewResult::sole()->mistakes)->toBe([$this->words[1]->id]);
});

test('opening part 5 again clears old pronunciation scores', function () {
    fakeAzure(95);
    $this->actingAs($this->user)->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json']);
    $this->actingAs($this->user)->get(route('lessons.review', [$this->course, $this->lesson, 5]));

    $this->actingAs($this->user)
        ->postJson(route('lessons.review.store', [$this->course, $this->lesson, 5]), [
            'duration_seconds' => 30,
            'answers' => $this->words->map(fn ($word) => ['word_id' => $word->id, 'answer' => null])->all(),
        ])
        ->assertJson(['correct_count' => 0]);
});

test('returns 503 when Azure is not configured', function () {
    config(['services.azure_speech.key' => null]);
    Http::fake();

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertStatus(503);

    Http::assertNothingSent();
});

test('returns 502 when Azure fails', function () {
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertStatus(502);
});

test('guests, words from other lessons and non-audio files are rejected', function () {
    Http::fake();
    $otherWord = Word::factory()->create(['audio_url' => 'https://example.com/audio.mp3']);

    $this->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $otherWord->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->post($this->url, ['word_id' => $this->words[0]->id, 'audio' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('audio');

    Http::assertNothingSent();
});

test('users without access to the lesson cannot use the scoring', function () {
    Http::fake();
    $paid = Lesson::factory()->published()->for($this->course)->hasAttached($this->words, ['sort_order' => 1])->create(['sort_order' => 2]);

    $this->actingAs($this->user)
        ->post(route('lessons.review.pronunciation', [$this->course, $paid]), ['word_id' => $this->words[0]->id, 'audio' => wavUpload()], ['Accept' => 'application/json'])
        ->assertForbidden();

    Http::assertNothingSent();
});
