<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Word;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A lesson's vocabulary as text, one word per line, as typed in the admin lesson form:
 *
 *     研究|yánjiū|nghiên cứu|noun:nghiên cứu|verb:tìm hiểu, nghiên cứu
 *
 * hanzi | tone-marked pinyin | Hán Việt (may be empty) | meanings, each "part_of_speech:meaning" or just "meaning".
 */
class LessonVocabulary
{
    /**
     * @return list<array{hanzi: string, pinyin: string, pinyin_number: string, han_viet: ?string, meanings: list<array{?string, string}>}>
     *
     * @throws InvalidArgumentException listing the invalid lines, e.g. "Line 2: ..."
     */
    public static function parse(?string $text): array
    {
        $entries = [];
        $errors = [];

        foreach (preg_split('/\R/u', (string) $text) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $entry = self::parseLine($line);
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Line '.($index + 1).': '.$e->getMessage();

                continue;
            }

            $key = $entry['hanzi'].'|'.$entry['pinyin_number'];
            if (isset($entries[$key])) {
                $errors[] = 'Line '.($index + 1).": {$entry['hanzi']} ({$entry['pinyin']}) is listed twice.";

                continue;
            }

            $entries[$key] = $entry;
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return array_values($entries);
    }

    /**
     * The lesson's words as text, in lesson order.
     *
     * @param  iterable<Word>  $words
     */
    public static function format(iterable $words): string
    {
        return collect($words)
            ->map(fn (Word $word): string => implode('|', [
                $word->hanzi,
                $word->pinyin,
                (string) $word->han_viet,
                ...array_map(fn (array $meaning): string => filled($meaning[0]) ? "{$meaning[0]}:{$meaning[1]}" : $meaning[1], $word->meanings),
            ]))
            ->implode("\n");
    }

    /**
     * Make the lesson's vocabulary match the text: words are found by hanzi + pinyin (or created), their Hán Việt
     * and meanings are updated, and the lesson's word list is set in line order. Words left out stay in the dictionary.
     */
    public static function sync(Lesson $lesson, ?string $text): void
    {
        $entries = self::parse($text);

        DB::transaction(function () use ($lesson, $entries): void {
            $words = Collection::make($entries)->mapWithKeys(function (array $entry, int $index) use ($lesson): array {
                $word = Word::firstOrNew(['hanzi' => $entry['hanzi'], 'pinyin_number' => $entry['pinyin_number']]);
                $word->fill(['pinyin' => $entry['pinyin'], 'han_viet' => $entry['han_viet'], 'meanings' => $entry['meanings']]);
                $word->hsk_level ??= $lesson->course?->hsk_level;
                $word->save();

                return [$word->id => ['sort_order' => $index + 1]];
            });

            $lesson->words()->sync($words->all());
        });
    }

    /**
     * @return array{hanzi: string, pinyin: string, pinyin_number: string, han_viet: ?string, meanings: list<array{?string, string}>}
     */
    private static function parseLine(string $line): array
    {
        $fields = array_map('trim', explode('|', $line));

        if (count($fields) < 4) {
            throw new InvalidArgumentException('expected hanzi|pinyin|Hán Việt|meaning, at least 4 parts separated by |.');
        }

        [$hanzi, $pinyin, $hanViet] = $fields;

        if ($hanzi === '' || mb_strlen($hanzi) > 50) {
            throw new InvalidArgumentException('the hanzi is missing or longer than 50 characters.');
        }

        $pinyinNumber = Pinyin::toNumbered($pinyin);
        if ($pinyinNumber === null || mb_strlen($pinyin) > 100) {
            throw new InvalidArgumentException("\"{$pinyin}\" is not valid tone-marked pinyin.");
        }

        if (mb_strlen($hanViet) > 100) {
            throw new InvalidArgumentException('the Hán Việt is longer than 100 characters.');
        }

        $meanings = [];
        foreach (array_slice($fields, 3) as $field) {
            if ($field === '') {
                continue;
            }

            // "noun:nghiên cứu" has a part of speech; "nghiên cứu" has none.
            if (! preg_match('/^([a-z_]+)\s*:(.*)$/u', $field, $match)) {
                $meanings[] = [null, $field];

                continue;
            }

            if (! isset(Word::PARTS_OF_SPEECH[$match[1]])) {
                throw new InvalidArgumentException("unknown part of speech \"{$match[1]}\" (use ".implode(', ', array_keys(Word::PARTS_OF_SPEECH)).').');
            }

            $meanings[] = [$match[1], trim($match[2])];
        }

        if ($meanings === [] || collect($meanings)->contains(fn (array $meaning): bool => $meaning[1] === '')) {
            throw new InvalidArgumentException('every word needs at least one meaning, and meanings cannot be empty.');
        }

        return [
            'hanzi' => $hanzi,
            'pinyin' => $pinyin,
            'pinyin_number' => $pinyinNumber,
            'han_viet' => $hanViet !== '' ? $hanViet : null,
            'meanings' => $meanings,
        ];
    }
}
