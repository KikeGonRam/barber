<?php

namespace App\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    protected $fillable = [
        'barbero_id',
        'title',
        'description',
        'work_date',
    ];

    public function barberUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barbero_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(WorkImage::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedWork::class);
    }

    public function isReactedBy(User $user): bool
    {
        return $this->reactions()->where('user_id', $user->id)->exists();
    }

    public function isSavedBy(User $user): bool
    {
        return $this->saves()->where('user_id', $user->id)->exists();
    }
}
