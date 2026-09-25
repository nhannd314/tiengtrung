<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'lesson_id', 'part', 'correct_count', 'total_questions', 'duration_seconds', 'mistakes', 'completed_at'])]
class LessonReviewResult extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'part' => 'integer',
            'correct_count' => 'integer',
            'total_questions' => 'integer',
            'duration_seconds' => 'integer',
            'mistakes' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Duration as m:ss (or h:mm:ss), e.g. "2:05".
     */
    public function formattedDuration(): string
    {
        return self::formatSeconds($this->duration_seconds);
    }

    public static function formatSeconds(int $seconds): string
    {
        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Percentage of correct answers, 0-100.
     */
    public function accuracy(): int
    {
        return $this->total_questions > 0 ? (int) round($this->correct_count / $this->total_questions * 100) : 0;
    }
}
