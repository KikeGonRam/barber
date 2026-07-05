<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasPublicCode
{
    public static function bootHasPublicCode(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->code)) {
                $model->code = static::generateUniqueCode();
            }
        });
    }

    protected static function generateUniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
