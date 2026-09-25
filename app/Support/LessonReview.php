<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Word;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds and grades the exercises of a lesson's review page (6 parts, one page each).
 */
class LessonReview
{
    public const OPTIONS_PER_QUESTION = 4;

    public const TOTAL_PARTS = 5;

    /** Parts a learner must finish before they can complete the lesson. */
    public const PARTS_REQUIRED_TO_COMPLETE = [1, 2, 3, 4];

    /** Correct answers across the required parts must be above this percentage to complete the lesson. */
    public const COMPLETION_ACCURACY = 90;

    /** Minimum Azure pronunciation score (0-100) for a spoken answer to count as correct. */
    public const PRONUNCIATION_PASS_SCORE = 60;

    /**
     * Parts that have been built. Each shows the word's `prompt` and asks for its `answer_field`.
     * Prompts: 'hanzi_pinyin_audio' (hanzi + pinyin + audio button), 'hanzi' (hanzi only), 'meaning'.
     * `input`: 'choice' (pick 1 of 4 options), 'typed' (type the word, checked by TypedAnswer)
     * or 'speech' (say the word, scored by Azure; see ReviewPronunciationController).
     * `option_hint` (optional, choice only) is a word field shown under each option, e.g. the pinyin under a hanzi option.
     *
     * @var array<int, array{title: string, instruction: string, prompt: string, answer_field: string, input: string, option_hint?: string}>
     */
    public const PARTS = [
        1 => ['title' => 'Chọn nghĩa đúng', 'instruction' => 'Nghe, nhìn chữ Hán và chọn nghĩa đúng.', 'prompt' => 'hanzi_pinyin_audio', 'answer_field' => 'meaning_text', 'input' => 'choice'],
        2 => ['title' => 'Chọn từ đúng', 'instruction' => 'Đọc nghĩa tiếng Việt và chọn từ đúng.', 'prompt' => 'meaning', 'answer_field' => 'hanzi', 'input' => 'choice', 'option_hint' => 'pinyin'],
        3 => ['title' => 'Nhận diện chữ Hán', 'instruction' => 'Chỉ nhìn chữ Hán (không có pinyin) và chọn nghĩa đúng.', 'prompt' => 'hanzi', 'answer_field' => 'meaning_text', 'input' => 'choice'],
        4 => ['title' => 'Viết từ', 'instruction' => 'Đọc nghĩa tiếng Việt và gõ từ: chữ Hán hoặc pinyin có thanh điệu (nǐ hǎo / ni3hao3).', 'prompt' => 'meaning', 'answer_field' => 'hanzi', 'input' => 'typed'],
        5 => ['title' => 'Luyện phát âm', 'instruction' => 'Đọc nghĩa tiếng Việt, bấm thu âm và nói từ tiếng Trung.', 'prompt' => 'meaning', 'answer_field' => 'hanzi', 'input' => 'speech'],
    ];

    /**
     * Session key holding this lesson's pronunciation scores, [word id => score].
     * Scores are written by the server after asking Azure, so they can't be forged.
     */
    public static function pronunciationSessionKey(Lesson $lesson): string
    {
        return "review.pronunciation.{$lesson->id}";
    }

    /**
     * What the learner must do before completing a lesson, e.g. "Ôn tập đủ phần 1, 2, 3, 4 với trên 90% câu đúng ...".
     */
    public static function completionRequirement(): string
    {
        $parts = implode(', ', self::PARTS_REQUIRED_TO_COMPLETE);

        return 'Ôn tập đủ phần '.$parts.' với trên '.self::COMPLETION_ACCURACY.'% câu đúng để hoàn thành bài học.';
    }

    public function __construct(private Lesson $lesson) {}

    public static function exists(int $part): bool
    {
        return isset(self::PARTS[$part]);
    }

    /**
     * One question per lesson word. Choice parts also get the correct answer and 3 wrong options.
     *
     * Wrong options come from the lesson's other words first, then from the rest of the course,
     * then from any word, so short lessons still get 4 options.
     *
     * Each option is ['value' => what gets submitted and graded, 'hint' => optional text shown under it].
     *
     * @return list<array{id: int, hanzi: string, pinyin: string, meaning: string, audio_url: ?string, answer: string, answer_label: string, options: list<array{value: string, hint: ?string}>}>
     */
    public function questions(int $part): array
    {
        $field = self::PARTS[$part]['answer_field'];
        $hintField = self::PARTS[$part]['option_hint'] ?? null;
        $isChoice = self::PARTS[$part]['input'] === 'choice';
        $words = $this->lesson->words;
        $distractorPool = $isChoice ? $this->distractorPool($words, $field) : collect();

        $option = fn (Word $word) => ['value' => $word->{$field}, 'hint' => $hintField ? $word->{$hintField} : null];

        return $words->map(function (Word $word) use ($field, $distractorPool, $option, $isChoice) {
            $answer = $word->{$field};
            $wrong = $distractorPool
                ->reject(fn (Word $candidate) => $candidate->{$field} === $answer)
                ->shuffle()
                ->take(self::OPTIONS_PER_QUESTION - 1)
                ->map($option);

            return [
                'id' => $word->id,
                'hanzi' => $word->hanzi,
                'pinyin' => $word->pinyin,
                'meaning' => $word->meaning_text,
                'audio_url' => $word->audio_src,
                'answer' => $answer,
                'answer_label' => $field === 'hanzi' ? "{$word->hanzi} ({$word->pinyin})" : $answer,
                'options' => $isChoice ? $wrong->push($option($word))->shuffle()->values()->all() : [],
            ];
        })->shuffle()->values()->all();
    }

    /**
     * Grade a part on the server, so scores can't be forged by the browser. Every word must be answered.
     *
     * @param  list<array{word_id: int, answer: ?string}>  $answers
     * @param  array<int, float>  $pronunciationScores  [word id => Azure score], for speech parts
     * @return array{correct: int, total: int, mistakes: list<int>}
     *
     * @throws ValidationException
     */
    public function grade(int $part, array $answers, array $pronunciationScores = []): array
    {
        $words = $this->lesson->words->keyBy('id');
        $answered = collect($answers)->pluck('answer', 'word_id');

        if ($answered->keys()->sort()->values()->all() !== $words->keys()->sort()->values()->all()) {
            throw ValidationException::withMessages(['answers' => "Bạn chưa trả lời hết các câu của phần {$part}."]);
        }

        $field = self::PARTS[$part]['answer_field'];
        $isCorrect = match (self::PARTS[$part]['input']) {
            'typed' => fn (Word $word) => TypedAnswer::matches($answered[$word->id], $word),
            // Skipped or never recorded words have no score and count as wrong.
            'speech' => fn (Word $word) => ($pronunciationScores[$word->id] ?? 0) >= self::PRONUNCIATION_PASS_SCORE,
            default => fn (Word $word) => $answered[$word->id] === $word->{$field},
        };

        $mistakes = $words->reject($isCorrect)->keys();

        return [
            'correct' => $words->count() - $mistakes->count(),
            'total' => $words->count(),
            'mistakes' => $mistakes->values()->all(),
        ];
    }

    /**
     * Words with distinct values of $field to use as wrong options.
     *
     * @param  Collection<int, Word>  $words
     * @return Collection<int, Word>
     */
    private function distractorPool(Collection $words, string $field): Collection
    {
        // The correct answer plus 3 wrong ones.
        $needed = self::OPTIONS_PER_QUESTION;
        $pool = $words->unique($field)->values();

        $fallbacks = [
            fn () => Word::whereHas('lessons', fn ($query) => $query->where('course_id', $this->lesson->course_id)),
            fn () => Word::query(),
        ];

        foreach ($fallbacks as $query) {
            if ($pool->count() >= $needed) {
                break;
            }

            $pool = $pool->concat(
                $query()->whereNotIn('id', $words->pluck('id'))->inRandomOrder()->limit($needed * 2)->get()
            )->unique($field)->values();
        }

        return $pool;
    }
}
