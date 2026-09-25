<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->word = Word::factory()->create([
        'hanzi' => '你好',
        'pinyin' => 'nǐ hǎo',
        'pinyin_number' => 'ni3 hao3',
        'han_viet' => 'nhĩ hảo',
        'meanings' => [['interjection', 'Xin chào']],
    ]);
});

test('header links to the vocabulary page', function () {
    $this->get(route('home'))->assertSee(route('words.index'));
});

test('vocabulary page shows the search form without results', function () {
    $this->get(route('words.index'))
        ->assertOk()
        ->assertSee('name="q"', false)
        ->assertDontSee('Tìm thấy')
        ->assertDontSee('nhĩ hảo');
});

test('words can be found by hanzi, pinyin, han viet or meaning', function (string $search) {
    Word::factory()->create(['hanzi' => '谢谢', 'pinyin' => 'xièxie', 'pinyin_number' => 'xie4 xie5', 'meanings' => [[null, 'cảm ơn']]]);

    $this->get(route('words.index', ['q' => $search]))
        ->assertOk()
        ->assertSee('你好')
        ->assertDontSee('谢谢');
})->with([
    'hanzi' => '你',
    'tone-marked pinyin' => 'nǐ hǎo',
    'numbered pinyin' => 'ni3 hao3',
    'pinyin without tones or spaces' => 'nihao',
    'han viet' => 'nhĩ hảo',
    'meaning, any case' => 'xin CHÀO',
]);

test('a search with no match says so', function () {
    $this->get(route('words.index', ['q' => 'không có']))
        ->assertOk()
        ->assertSee('Không tìm thấy từ nào');
});

test('results list the published lessons containing the word', function () {
    $course = Course::factory()->published()->create(['title' => 'Khoá HSK 1']);
    $lesson = Lesson::factory()->published()->for($course)->create(['title' => 'Bài 1: Chào hỏi']);
    $draftLesson = Lesson::factory()->for($course)->create(['title' => 'Bài nháp']);
    $hiddenCourseLesson = Lesson::factory()->published()->for(Course::factory())->create(['title' => 'Bài của khoá ẩn']);
    $this->word->lessons()->attach([$lesson->id, $draftLesson->id, $hiddenCourseLesson->id]);

    $this->get(route('words.index', ['q' => '你好']))
        ->assertOk()
        ->assertSee('Bài 1: Chào hỏi')
        ->assertSee('Khoá HSK 1')
        ->assertSee(route('lessons.show', [$course, $lesson]))
        ->assertDontSee('Bài nháp')
        ->assertDontSee('Bài của khoá ẩn');
});

test('words in no lesson are still shown', function () {
    $this->get(route('words.index', ['q' => '你好']))
        ->assertOk()
        ->assertSee('Chưa có bài học nào chứa từ này.');
});
