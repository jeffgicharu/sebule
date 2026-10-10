<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a unique slug made from its name when none was supplied.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlugFor($model->name);
            }
        });
    }

    public function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
