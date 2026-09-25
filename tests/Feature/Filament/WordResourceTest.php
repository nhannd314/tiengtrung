<?php

use App\Filament\Resources\Words\Pages\CreateWord;
use App\Filament\Resources\Words\Pages\EditWord;
use App\Filament\Resources\Words\Pages\ListWords;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * Items of the "meanings" repeater, in the shape the form holds them: [['part_of_speech' => ..., 'meaning' => ...], ...].
 * The form turns them into / from the stored [part_of_speech, meaning] pairs.
 *
 * @param  array{?string, string}  ...$meanings
 */
function meaningItems(array ...$meanings): array
{
    return array_map(fn (array $meaning) => ['part_of_speech' => $meaning[0], 'meaning' => $meaning[1]], $meanings);
}

function wordFormData(array $overrides = []): array
{
    return [
        'hanzi' => '好',
        'pinyin' => 'hǎo',
        'pinyin_number' => 'hao3',
        'han_viet' => 'hảo',
        'hsk_level' => 1,
        'meanings' => meaningItems(['adjective', 'tốt'], ['adjective', 'khoẻ'], ['adverb', 'rất']),
        'examples' => [
            ['sentence' => '我很好。', 'pinyin' => 'Wǒ hěn hǎo.', 'meaning' => 'Tôi rất khoẻ.', 'audio_url' => null],
            ['sentence' => '好吃！', 'pinyin' => 'Hǎochī!', 'meaning' => 'Ngon!', 'audio_url' => null],
        ],
        ...$overrides,
    ];
}

test('admins can create a word with several meanings and examples', function () {
    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $word = Word::sole();
    expect($word->meanings)->toBe([['adjective', 'tốt'], ['adjective', 'khoẻ'], ['adverb', 'rất']])
        ->and($word->meaning_text)->toBe('tốt; khoẻ; rất')
        ->and($word->part_of_speech_labels)->toBe(['Tính từ', 'Phó từ'])
        ->and($word->examples)->toBe([
            ['sentence' => '我很好。', 'pinyin' => 'Wǒ hěn hǎo.', 'meaning' => 'Tôi rất khoẻ.', 'audio_url' => null],
            ['sentence' => '好吃！', 'pinyin' => 'Hǎochī!', 'meaning' => 'Ngon!', 'audio_url' => null],
        ])
        // Stored as plain JSON lists with readable Vietnamese.
        ->and(DB::table('words')->value('meanings'))->toContain('khoẻ')
        ->and(json_decode(DB::table('words')->value('examples'), true))->toBeList();
});

test('the edit form loads meanings and examples and saves changes', function () {
    $word = Word::factory()->create(wordFormData(['meanings' => [['adjective', 'tốt'], [null, 'hay']], 'examples' => null]));

    Livewire::test(EditWord::class, ['record' => $word->getRouteKey()])
        ->assertSet('data.meanings', fn (array $items) => array_values($items) === meaningItems(['adjective', 'tốt'], [null, 'hay']))
        ->fillForm(['meanings' => meaningItems(['adjective', 'hay'], [null, 'khoẻ']), 'examples' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($word->fresh())
        ->meanings->toBe([['adjective', 'hay'], [null, 'khoẻ']])
        ->examples->toBeEmpty();
});

test('at least one meaning is required and example sentences cannot be blank', function () {
    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['meanings' => []]))
        ->call('create')
        ->assertHasFormErrors(['meanings']);

    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['examples' => [['sentence' => '', 'pinyin' => 'x']]]))
        ->call('create')
        ->assertHasFormErrors();

    expect(Word::count())->toBe(0);
});

test('the same hanzi can exist with another reading, but not twice with the same one', function () {
    Word::factory()->create(['hanzi' => '行', 'pinyin' => 'xíng', 'pinyin_number' => 'xing2']);

    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['hanzi' => '行', 'pinyin' => 'xíng', 'pinyin_number' => 'xing2']))
        ->call('create')
        ->assertHasFormErrors(['pinyin_number' => 'unique']);

    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['hanzi' => '行', 'pinyin' => 'háng', 'pinyin_number' => 'hang2']))
        ->call('create')
        ->assertHasNoFormErrors();
});

test('pinyin with tone numbers must have a tone per syllable', function (string $value, bool $valid) {
    $test = Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['pinyin_number' => $value]))
        ->call('create');

    $valid ? $test->assertHasNoFormErrors() : $test->assertHasFormErrors(['pinyin_number' => 'regex']);
})->with([
    ['xie4xie5', true],
    ['lü4', true],
    ['nihao', false],
    ['ni3 hao3', false],
]);

test('uploaded images are shown from the public disk and cleaned up when replaced', function () {
    Storage::fake(Word::IMAGE_DISK);

    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData(['image_url' => UploadedFile::fake()->image('hao.png')]))
        ->call('create')
        ->assertHasNoFormErrors();

    $word = Word::sole();
    Storage::disk(Word::IMAGE_DISK)->assertExists($word->image_url);
    expect($word->image_src)->toBe(Storage::disk(Word::IMAGE_DISK)->url($word->image_url));

    $old = $word->image_url;
    $word->update(['image_url' => 'https://example.com/hao.png']);
    Storage::disk(Word::IMAGE_DISK)->assertMissing($old);
    expect($word->fresh()->image_src)->toBe('https://example.com/hao.png');
});

test('words can be searched by meaning in the table', function () {
    $hao = Word::factory()->create(['hanzi' => '好', 'meanings' => [['adjective', 'tốt'], ['adjective', 'khoẻ']]]);
    $cha = Word::factory()->create(['hanzi' => '茶', 'meanings' => [['noun', 'trà']]]);

    Livewire::test(ListWords::class)
        ->searchTable('khoẻ')
        ->assertCanSeeTableRecords([$hao])
        ->assertCanNotSeeTableRecords([$cha]);
});

test('words can be filtered by the part of speech of any meaning', function () {
    $hao = Word::factory()->create(['meanings' => [['adjective', 'tốt'], ['adverb', 'rất']]]);
    $cha = Word::factory()->create(['meanings' => [['noun', 'trà']]]);

    Livewire::test(ListWords::class)
        ->filterTable('part_of_speech', 'adverb')
        ->assertCanSeeTableRecords([$hao])
        ->assertCanNotSeeTableRecords([$cha]);
});

test('meanings accept a single string or plain strings and drop blank items', function () {
    $word = Word::factory()->create(['meanings' => 'xin chào']);
    expect($word->fresh()->meanings)->toBe([[null, 'xin chào']]);

    $word->update(['meanings' => [['adjective', ' tốt '], ['', ''], 'hay']]);
    expect($word->fresh()->meanings)->toBe([['adjective', 'tốt'], [null, 'hay']])
        ->and($word->fresh()->meaning_text)->toBe('tốt; hay');
});

test('audio for the word and its examples can be uploaded', function () {
    Storage::fake(Word::AUDIO_DISK);

    Livewire::test(CreateWord::class)
        ->fillForm(wordFormData([
            'audio_url' => UploadedFile::fake()->create('hao.mp3', 20, 'audio/mpeg'),
            // Inside a repeater, a file upload's state is a list of files.
            'examples' => [
                ['sentence' => '我很好。', 'pinyin' => 'Wǒ hěn hǎo.', 'meaning' => 'Tôi rất khoẻ.', 'audio_url' => [UploadedFile::fake()->create('wo.mp3', 20, 'audio/mpeg')]],
            ],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $word = Word::sole();
    expect($word->audio_url)->toStartWith(Word::AUDIO_DIRECTORY.'/')
        ->and($word->examples[0]['audio_url'])->toBeString()->toStartWith(Word::EXAMPLE_AUDIO_DIRECTORY.'/');
    Storage::disk(Word::AUDIO_DISK)->assertExists([$word->audio_url, $word->examples[0]['audio_url']]);
});
