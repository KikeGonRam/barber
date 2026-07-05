<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug($model->slugSource());
            }
        });
    }

    abstract protected function slugSource(): string;

    protected function generateUniqueSlug(string $source): string
    {
        $base = Str::slug($source);

        if (empty($base)) {
            $base = 'item';
        }

        $slug = $base;
        $i    = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
