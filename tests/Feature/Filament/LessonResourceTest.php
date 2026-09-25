<?php

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->course = Course::factory()->create();
});

test('creating a lesson without a slug generates one', function () {
    Livewire::test(CreateLesson::class)
        ->fillForm([
            'course_id' => $this->course->id,
            'title' => 'Chào hỏi',
            'content' => '<p>Xin chào</p>',
            'duration_minutes' => 30,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $lesson = Lesson::sole();

    expect($lesson->slug)->toBe('chao-hoi')
        ->and($lesson->course_id)->toBe($this->course->id)
        ->and($lesson->content)->toContain('Xin chào');
});

test('lesson slug only has to be unique within its course', function () {
    Lesson::factory()->for($this->course)->create(['slug' => 'chao-hoi']);

    Livewire::test(CreateLesson::class)
        ->fillForm(['course_id' => $this->course->id, 'title' => 'Chào hỏi', 'slug' => 'chao-hoi'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(CreateLesson::class)
        ->fillForm(['course_id' => Course::factory()->create()->id, 'title' => 'Chào hỏi', 'slug' => 'chao-hoi'])
        ->call('create')
        ->assertHasNoFormErrors();
});

test('publishing a lesson fills in the publish date', function () {
    Livewire::test(CreateLesson::class)
        ->set('data.is_published', true)
        ->assertNotSet('data.published_at', null);
});

test('attachments are uploaded to the private disk under their original names', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'course_id' => $this->course->id,
            'title' => 'Chào hỏi',
            'attachments' => [
                UploadedFile::fake()->create('Bài 1.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('nghe.mp3', 500, 'audio/mpeg'),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $lesson = Lesson::sole();
    expect($lesson->attachments)->toHaveCount(2)
        ->and($lesson->attachmentFiles()->pluck('name')->sort()->values()->all())->toBe(['Bài 1.pdf', 'nghe.mp3']);

    foreach ($lesson->attachments as $path) {
        expect($path)->toMatch('~^lesson-attachments/[0-9A-Z]{26}/~');
        Storage::disk(Lesson::ATTACHMENTS_DISK)->assertExists($path);
    }
});

test('file types and sizes are validated', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'course_id' => $this->course->id,
            'title' => 'Chào hỏi',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])
        ->call('create')
        ->assertHasFormErrors(['attachments']);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'course_id' => $this->course->id,
            'title' => 'Chào hỏi',
            'attachments' => [UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf')],
        ])
        ->call('create')
        ->assertHasFormErrors(['attachments']);

    expect(Lesson::count())->toBe(0);
});

test('the edit form loads attachments stored as objects and plain paths', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $disk = Storage::disk(Lesson::ATTACHMENTS_DISK);
    $disk->put('lesson-attachments/A/bai-1.pdf', 'pdf');
    $disk->put('lesson-attachments/B/nghe.mp3', 'mp3');

    $lesson = Lesson::factory()->for($this->course)->create([
        'attachments' => ['lesson-attachments/A/bai-1.pdf', ['path' => 'lesson-attachments/B/nghe.mp3', 'name' => 'Nghe.mp3']],
    ]);

    Livewire::test(EditLesson::class, ['record' => $lesson->getRouteKey()])
        ->assertSet('data.attachments', fn (array $files) => array_values($files) === ['lesson-attachments/A/bai-1.pdf', 'lesson-attachments/B/nghe.mp3'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lesson->fresh()->attachments)->toBe(['lesson-attachments/A/bai-1.pdf', 'lesson-attachments/B/nghe.mp3']);
});

test('files removed from a lesson are deleted from the disk', function () {
    Storage::fake(Lesson::ATTACHMENTS_DISK);
    $disk = Storage::disk(Lesson::ATTACHMENTS_DISK);
    $disk->put('lesson-attachments/A/keep.pdf', 'pdf');
    $disk->put('lesson-attachments/B/remove.pdf', 'pdf');

    $lesson = Lesson::factory()->for($this->course)->create([
        'attachments' => ['lesson-attachments/A/keep.pdf', 'lesson-attachments/B/remove.pdf'],
    ]);

    $lesson->update(['attachments' => ['lesson-attachments/A/keep.pdf']]);

    $disk->assertExists('lesson-attachments/A/keep.pdf');
    $disk->assertMissing('lesson-attachments/B/remove.pdf');
    $disk->assertMissing('lesson-attachments/B');

    $lesson->delete();
    $disk->assertExists('lesson-attachments/A/keep.pdf');

    $lesson->forceDelete();
    $disk->assertMissing('lesson-attachments/A/keep.pdf');
});

test('slug is required when editing', function () {
    $lesson = Lesson::factory()->for($this->course)->create();

    Livewire::test(EditLesson::class, ['record' => $lesson->getRouteKey()])
        ->fillForm(['slug' => null])
        ->call('save')
        ->assertHasFormErrors(['slug' => 'required']);
});

test('vocabulary typed in the lesson form creates words and sets the lesson word list in order', function () {
    $this->course->update(['hsk_level' => 3]);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'course_id' => $this->course->id,
            'title' => 'Nghiên cứu',
            'vocabulary' => "研究|yánjiū|nghiên cứu|noun:nghiên cứu|verb:tìm hiểu, nghiên cứu\n\n谢谢|xièxie||cảm ơn",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $words = Lesson::sole()->words;

    expect($words->pluck('hanzi')->all())->toBe(['研究', '谢谢'])
        ->and($words[0])
        ->pinyin->toBe('yánjiū')
        ->pinyin_number->toBe('yan2jiu1')
        ->han_viet->toBe('nghiên cứu')
        ->meanings->toBe([['noun', 'nghiên cứu'], ['verb', 'tìm hiểu, nghiên cứu']])
        ->hsk_level->toBe(3)
        ->and($words[1])
        ->pinyin_number->toBe('xie4xie5')
        ->han_viet->toBeNull()
        ->meanings->toBe([[null, 'cảm ơn']]);
});

test('the edit form shows the vocabulary and saving it updates, reorders and removes words', function () {
    $hao = Word::factory()->create(['hanzi' => '好', 'pinyin' => 'hǎo', 'pinyin_number' => 'hao3', 'han_viet' => 'hảo', 'meanings' => [['adjective', 'tốt']]]);
    $cha = Word::factory()->create(['hanzi' => '茶', 'pinyin' => 'chá', 'pinyin_number' => 'cha2', 'han_viet' => null, 'meanings' => 'trà']);
    $lesson = Lesson::factory()->for($this->course)
        ->hasAttached($hao, ['sort_order' => 1, 'note' => 'Chú ý'])
        ->hasAttached($cha, ['sort_order' => 2])
        ->create();

    Livewire::test(EditLesson::class, ['record' => $lesson->getRouteKey()])
        ->assertSet('data.vocabulary', "好|hǎo|hảo|adjective:tốt\n茶|chá||trà")
        ->fillForm(['vocabulary' => "研究|yánjiū||verb:nghiên cứu\n好|hǎo|hảo|adjective:tốt|adverb:rất"])
        ->call('save')
        ->assertHasNoFormErrors();

    $words = $lesson->fresh()->words;
    expect($words->pluck('hanzi')->all())->toBe(['研究', '好'])
        ->and($words[1]->is($hao))->toBeTrue()
        ->and($words[1]->pivot->note)->toBe('Chú ý')
        ->and($hao->fresh()->meanings)->toBe([['adjective', 'tốt'], ['adverb', 'rất']])
        // Words removed from the lesson stay in the dictionary.
        ->and($cha->fresh())->not->toBeNull();
});

test('invalid vocabulary lines are reported with their line numbers and nothing is saved', function (string $vocabulary, string $error) {
    Livewire::test(CreateLesson::class)
        ->fillForm(['course_id' => $this->course->id, 'title' => 'Bài 1', 'vocabulary' => $vocabulary])
        ->call('create')
        ->assertHasFormErrors(['vocabulary'])
        ->assertSee($error);

    expect(Lesson::count())->toBe(0)->and(Word::count())->toBe(0);
})->with([
    'too few parts' => ["好|hǎo|hảo|adjective:tốt\n研究|yánjiū", 'Line 2: expected hanzi|pinyin|Hán Việt|meaning'],
    'invalid pinyin' => ['研究|abcxyz||nghiên cứu', 'Line 1: "abcxyz" is not valid tone-marked pinyin.'],
    'unknown part of speech' => ['研究|yánjiū||nuon:nghiên cứu', 'Line 1: unknown part of speech "nuon"'],
    'duplicate word' => ["好|hǎo||tốt\n好|hǎo||hay", 'Line 2: 好 (hǎo) is listed twice.'],
]);
