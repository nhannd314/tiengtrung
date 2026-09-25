<?php

namespace App\Support;

use Normalizer;

/**
 * Converts tone-marked pinyin to tone numbers: "yánjiū" => "yan2jiu1", "xièxie" => "xie4xie5".
 */
class Pinyin
{
    private const TONE_MARKS = [
        'ā' => ['a', 1], 'á' => ['a', 2], 'ǎ' => ['a', 3], 'à' => ['a', 4],
        'ē' => ['e', 1], 'é' => ['e', 2], 'ě' => ['e', 3], 'è' => ['e', 4],
        'ī' => ['i', 1], 'í' => ['i', 2], 'ǐ' => ['i', 3], 'ì' => ['i', 4],
        'ō' => ['o', 1], 'ó' => ['o', 2], 'ǒ' => ['o', 3], 'ò' => ['o', 4],
        'ū' => ['u', 1], 'ú' => ['u', 2], 'ǔ' => ['u', 3], 'ù' => ['u', 4],
        'ǖ' => ['ü', 1], 'ǘ' => ['ü', 2], 'ǚ' => ['ü', 3], 'ǜ' => ['ü', 4],
    ];

    /**
     * One syllable: optional initial + final. A final may not be followed by a vowel, so "xinan" splits as
     * xi-nan and "fangan" as fan-gan, as pinyin spelling rules say (fāng'àn needs its apostrophe).
     */
    private const SYLLABLE = '/\G(?:zh|ch|sh|[bpmfdtnlgkhjqxrzcsyw])?'
        .'(?:iang|iong|uang|ang|eng|ing|ong|ian|iao|uai|uan|üan|ai|ei|ao|ou|an|en|in|un|ün|ia|ie|iu|ua|uo|ui|üe|ue|er|a|o|e|i|u|ü)'
        .'(?![aeiouü])/u';

    /**
     * Null when the text isn't valid pinyin (unknown syllable, or two tone marks in one syllable).
     */
    public static function toNumbered(string $pinyin): ?string
    {
        if (class_exists(Normalizer::class)) {
            $pinyin = Normalizer::normalize($pinyin, Normalizer::FORM_C) ?: $pinyin;
        }

        $result = '';

        // Spaces, apostrophes and hyphens always separate syllables.
        foreach (preg_split("/[\\s'’\\-]+/u", mb_strtolower(trim($pinyin)), flags: PREG_SPLIT_NO_EMPTY) as $chunk) {
            $letters = '';
            $tones = []; // tone by letter position

            foreach (mb_str_split(str_replace('v', 'ü', $chunk)) as $char) {
                [$letter, $tone] = self::TONE_MARKS[$char] ?? [$char, null];
                if ($tone !== null) {
                    $tones[mb_strlen($letters)] = $tone;
                }
                $letters .= $letter;
            }

            if (! preg_match_all(self::SYLLABLE, $letters, $matches) || implode('', $matches[0]) !== $letters) {
                return null;
            }

            $position = 0;
            foreach ($matches[0] as $syllable) {
                $length = mb_strlen($syllable);
                $syllableTones = array_filter($tones, fn (int $at) => $at >= $position && $at < $position + $length, ARRAY_FILTER_USE_KEY);

                if (count($syllableTones) > 1) {
                    return null;
                }

                $result .= $syllable.(reset($syllableTones) ?: 5);
                $position += $length;
            }
        }

        return $result === '' ? null : $result;
    }
}
