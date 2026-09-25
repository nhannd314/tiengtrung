<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['course_id', 'title', 'slug', 'summary', 'content', 'attachments', 'video_url', 'duration_minutes', 'sort_order', 'is_free', 'is_published', 'published_at'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /** Disk holding attachment files. Private: files are downloaded through LessonAttachmentController. */
    public const ATTACHMENTS_DISK = 'local';

    /** Attachment extensions played with an <audio> player on the lesson page. */
    public const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'opus', 'flac'];

    /**
     * Delete attachment files from the disk once no lesson refers to them any more
     * (removed in the admin form, or the lesson force-deleted). Soft-deleted lessons keep their files.
     */
    protected static function booted(): void
    {
        static::updated(function (Lesson $lesson): void {
            if ($lesson->wasChanged('attachments')) {
                self::deleteAttachmentFiles(
                    self::attachmentPaths($lesson->getOriginal('attachments'))->diff(self::attachmentPaths($lesson->attachments)),
                );
            }
        });

        static::forceDeleted(fn (Lesson $lesson) => self::deleteAttachmentFiles(self::attachmentPaths($lesson->attachments)));
    }

    /**
     * @return Collection<int, string>
     */
    private static function attachmentPaths(?array $attachments): Collection
    {
        return collect($attachments ?? [])
            ->map(fn (string|array $item) => is_array($item) ? ($item['path'] ?? null) : $item)
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $paths
     */
    private static function deleteAttachmentFiles(Collection $paths): void
    {
        $disk = Storage::disk(self::ATTACHMENTS_DISK);

        foreach ($paths as $path) {
            $disk->delete($path);

            // Uploads live in their own folder (lesson-attachments/{ulid}/name); remove it once empty.
            $folder = dirname($path);
            if (str_starts_with($folder, 'lesson-attachments/') && $disk->files($folder) === []) {
                $disk->deleteDirectory($folder);
            }
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_free' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    /**
     * Lesson slugs only need to be unique within their course.
     */
    protected function slugScope(Builder $query): void
    {
        $query->where('course_id', $this->course_id);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<LessonReviewResult, $this>
     */
    public function reviewResults(): HasMany
    {
        return $this->hasMany(LessonReviewResult::class);
    }

    /**
     * Attachments normalised for display and download. Items may be stored as a plain path
     * (what Filament's multiple FileUpload saves) or as {"path": ..., "name": ...}.
     *
     * @return Collection<int, array{index: int, path: string, name: string, extension: string, size: ?int, is_audio: bool}>
     */
    public function attachmentFiles(): Collection
    {
        $disk = Storage::disk(self::ATTACHMENTS_DISK);

        return collect($this->attachments ?? [])
            ->map(fn (string|array $item) => is_string($item) ? ['path' => $item] : $item)
            ->filter(fn (array $item) => filled($item['path'] ?? null))
            ->map(function (array $item, int $index) use ($disk): array {
                $extension = strtolower(pathinfo($item['name'] ?? $item['path'], PATHINFO_EXTENSION));

                return [
                    'index' => $index,
                    'path' => $item['path'],
                    'name' => $item['name'] ?? basename($item['path']),
                    'extension' => $extension,
                    'size' => $disk->exists($item['path']) ? $disk->size($item['path']) : null,
                    'is_audio' => in_array($extension, self::AUDIO_EXTENSIONS, true),
                ];
            });
    }

    /**
     * The lesson's vocabulary.
     *
     * @return BelongsToMany<Word, $this>
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class)
            ->withPivot(['sort_order', 'note'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lesson_progress')
            ->withPivot(['started_at', 'completed_at', 'last_viewed_at'])
            ->withTimestamps();
    }

    /**
     * Whether the lesson is visible at all (drafts are only visible to admins).
     */
    public function isVisibleTo(?User $user): bool
    {
        return ($this->is_published && $this->course->is_published) || (bool) $user?->isAdmin();
    }

    /**
     * Whether the user may study the lesson: free lessons are open to everyone, others need enrollment.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if (! $this->isVisibleTo($user)) {
            return false;
        }

        return $this->is_free || (bool) $user?->isAdmin() || (bool) $user?->isEnrolledIn($this->course);
    }

    /**
     * YouTube embed URL for the lesson video, or null when the video is not on YouTube.
     */
    protected function youtubeEmbedUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->video_url) {
                return null;
            }

            preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $this->video_url, $matches);

            return isset($matches[1]) ? 'https://www.youtube-nocookie.com/embed/'.$matches[1] : null;
        });
    }

    /**
     * Only lessons that are visible to learners.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
