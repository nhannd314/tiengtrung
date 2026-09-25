<?php

namespace App\Support;

use App\Models\Word;
use Normalizer;

/**
 * Checks a typed answer against a word: the hanzi, or pinyin with tones (marks "nǐ hǎo" or numbers "ni3hao3").
 * Case, spaces and the neutral tone (5 or no number) are ignored. Pinyin without tones doesn't count.
 *
 * Mirrored in resources/js/app.js (pinyinKey) for instant feedback; the server result is the one saved.
 */
class TypedAnswer
{
    private const TONE_MARKS = [
        'ā' => ['a', 1], 'á' => ['a', 2], 'ǎ' => ['a', 3], 'à' => ['a', 4],
        'ē' => ['e', 1], 'é' => ['e', 2], 'ě' => ['e', 3], 'è' => ['e', 4],
        'ī' => ['i', 1], 'í' => ['i', 2], 'ǐ' => ['i', 3], 'ì' => ['i', 4],
        'ō' => ['o', 1], 'ó' => ['o', 2], 'ǒ' => ['o', 3], 'ò' => ['o', 4],
        'ū' => ['u', 1], 'ú' => ['u', 2], 'ǔ' => ['u', 3], 'ù' => ['u', 4],
        'ǖ' => ['v', 1], 'ǘ' => ['v', 2], 'ǚ' => ['v', 3], 'ǜ' => ['v', 4],
    ];

    public static function matches(?string $input, Word $word): bool
    {
        $input = trim((string) $input);

        if ($input === '') {
            return false;
        }

        if (self::withoutSpaces($input) === self::withoutSpaces($word->hanzi)) {
            return true;
        }

        $key = self::pinyinKey($input);

        return $key !== null && $key === self::pinyinKey($word->pinyin);
    }

    /**
     * Letters plus the tones in order, e.g. "nǐ hǎo" and "ni3hao3" both become "nihao|33".
     * Null when there are no pinyin letters or no tones at all.
     */
    public static function pinyinKey(string $pinyin): ?string
    {
        if (class_exists(Normalizer::class)) {
            $pinyin = Normalizer::normalize($pinyin, Normalizer::FORM_C) ?: $pinyin;
        }

        $letters = '';
        $tones = '';

        foreach (mb_str_split(mb_strtolower($pinyin)) as $char) {
            if (isset(self::TONE_MARKS[$char])) {
                [$letter, $tone] = self::TONE_MARKS[$char];
                $letters .= $letter;
                $tones .= $tone;
            } elseif (in_array($char, ['1', '2', '3', '4'], true)) {
                $tones .= $char;
            } elseif ($char === 'ü' || $char === 'v') {
                $letters .= 'v';
            } elseif (preg_match('/^[a-z]$/', $char)) {
                $letters .= $char;
            }
            // Anything else (spaces, apostrophes, neutral tone 5/0) is ignored.
        }

        return $letters === '' || $tones === '' ? null : "{$letters}|{$tones}";
    }

    private static function withoutSpaces(string $text): string
    {
        return preg_replace('/\s+/u', '', $text);
    }
}
