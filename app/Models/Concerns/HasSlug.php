<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Automatically generates a unique slug when a model is created without one.
 *
 * Models may override slugSourceColumn(), slugColumn() and slugScope() to customize the behavior.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
            $column = $model->slugColumn();

            if (blank($model->{$column})) {
                $model->{$column} = $model->generateUniqueSlug((string) $model->{$model->slugSourceColumn()});
            }
        });
    }

    /**
     * The attribute the slug is generated from.
     */
    public function slugSourceColumn(): string
    {
        return 'title';
    }

    /**
     * The attribute the slug is stored in.
     */
    public function slugColumn(): string
    {
        return 'slug';
    }

    /**
     * Restrict the uniqueness check, e.g. to slugs within the same parent.
     */
    protected function slugScope(Builder $query): void
    {
        //
    }

    /**
     * Build a slug from the given value, appending -2, -3, ... until it is unique.
     */
    public function generateUniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where($this->slugColumn(), $slug);

        // Soft-deleted rows still occupy the unique index.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        $this->slugScope($query);

        return $query->exists();
    }
}
