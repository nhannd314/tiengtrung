<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\LessonReview;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'phone', 'address', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /** Disk holding avatar images (uploaded from the admin user form or the profile page). */
    public const AVATAR_DISK = 'public';

    /**
     * Remove replaced or orphaned avatar files.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('avatar') && filled($old = $user->getOriginal('avatar'))) {
                Storage::disk(self::AVATAR_DISK)->delete($old);
            }
        });

        static::deleted(function (User $user): void {
            if (filled($user->avatar)) {
                Storage::disk(self::AVATAR_DISK)->delete($user->avatar);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Only active admins may access the Filament admin panel.
     * (Filament has its own login page, so the is_active check of the site login doesn't apply there.)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isAdmin() && $this->is_active;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar ? Storage::disk(self::AVATAR_DISK)->url($this->avatar) : null);
    }

    /**
     * Courses created by this user (admin).
     *
     * @return HasMany<Course, $this>
     */
    public function createdCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function enrolledCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
            ->withPivot(['enrolled_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Lessons the user has started, with progress on the pivot.
     *
     * @return BelongsToMany<Lesson, $this>
     */
    public function lessonProgress(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_progress')
            ->withPivot(['started_at', 'completed_at', 'last_viewed_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<LessonReviewResult, $this>
     */
    public function lessonReviewResults(): HasMany
    {
        return $this->hasMany(LessonReviewResult::class);
    }

    /**
     * The user's flashcard deck, with spaced-repetition state on the pivot.
     *
     * @return BelongsToMany<Word, $this>
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'user_words')
            ->withPivot(['is_favorite', 'ease_factor', 'interval_days', 'repetitions', 'correct_count', 'wrong_count', 'due_at', 'last_reviewed_at'])
            ->withTimestamps();
    }

    /**
     * Flashcards due for review now.
     *
     * @return BelongsToMany<Word, $this>
     */
    public function dueWords(): BelongsToMany
    {
        return $this->words()->wherePivot('due_at', '<=', now());
    }

    public function isEnrolledIn(Course $course): bool
    {
        return $this->enrolledCourses()->whereKey($course->id)->exists();
    }

    /**
     * Record that the user opened a lesson, keeping the first start time.
     */
    public function recordLessonView(Lesson $lesson): void
    {
        if ($this->lessonProgress()->whereKey($lesson->id)->exists()) {
            $this->lessonProgress()->updateExistingPivot($lesson->id, ['last_viewed_at' => now()]);

            return;
        }

        $this->lessonProgress()->attach($lesson->id, ['started_at' => now(), 'last_viewed_at' => now()]);
    }

    /**
     * Whether the user has reviewed every required part of the lesson with enough correct answers
     * (all parts together above LessonReview::COMPLETION_ACCURACY percent).
     */
    public function canCompleteLesson(Lesson $lesson): bool
    {
        $results = $this->lessonReviewResults()
            ->where('lesson_id', $lesson->id)
            ->whereIn('part', LessonReview::PARTS_REQUIRED_TO_COMPLETE)
            ->get(['correct_count', 'total_questions']);

        $total = $results->sum('total_questions');

        return $results->count() === count(LessonReview::PARTS_REQUIRED_TO_COMPLETE)
            && $total > 0
            && $results->sum('correct_count') / $total * 100 > LessonReview::COMPLETION_ACCURACY;
    }

    /**
     * Mark a lesson complete, and the course too once every published lesson is done.
     */
    public function completeLesson(Lesson $lesson): void
    {
        $this->recordLessonView($lesson);
        $this->lessonProgress()->updateExistingPivot($lesson->id, ['completed_at' => now()]);

        $course = $lesson->course;
        $remaining = $course->lessons()->published()
            ->whereDoesntHave('learners', fn ($query) => $query
                ->whereKey($this->id)
                ->whereNotNull('lesson_progress.completed_at'))
            ->exists();

        if (! $remaining && $this->isEnrolledIn($course)) {
            $this->enrolledCourses()->updateExistingPivot($course->id, ['completed_at' => now()]);
        }
    }
}
