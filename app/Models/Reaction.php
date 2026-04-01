<?php

namespace App\Models;

use Database\Factories\ReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    /** @use HasFactory<ReactionFactory> */
    use HasFactory;

    protected $fillable = [
        'work_id',
        'user_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
