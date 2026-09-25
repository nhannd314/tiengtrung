<?php

namespace Database\Factories;

use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    /**
     * Sample syllables as [tone-marked, numbered].
     *
     * @var list<array{string, string}>
     */
    protected const SYLLABLES = [
        ['mā', 'ma1'], ['hǎo', 'hao3'], ['shì', 'shi4'], ['rén', 'ren2'], ['xué', 'xue2'],
        ['zhōng', 'zhong1'], ['guó', 'guo2'], ['yǔ', 'yu3'], ['péng', 'peng2'], ['chī', 'chi1'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A random pair of CJK characters; the first is unique to avoid (hanzi, pinyin_number) collisions.
        $hanzi = mb_chr(fake()->unique()->numberBetween(0x4E00, 0x9FA5)).mb_chr(fake()->numberBetween(0x4E00, 0x9FA5));
        [$first, $second] = [fake()->randomElement(self::SYLLABLES), fake()->randomElement(self::SYLLABLES)];

        return [
            'hanzi' => $hanzi,
            'traditional' => null,
            'pinyin' => $first[0].$second[0],
            'pinyin_number' => $first[1].$second[1],
            'han_viet' => null,
            'meanings' => [[fake()->randomElement(['noun', 'verb', 'adjective', 'adverb', 'pronoun']), fake()->words(3, true)]],
            'hsk_level' => fake()->numberBetween(1, 6),
            'stroke_count' => fake()->numberBetween(2, 30),
            'note' => null,
            'audio_url' => null,
            'image_url' => null,
        ];
    }

    /**
     * Set the word's HSK level.
     */
    public function hsk(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'hsk_level' => $level,
        ]);
    }
}
