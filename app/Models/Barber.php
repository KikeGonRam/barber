<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Barber extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'especialidades',
        'foto',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class, 'barbero_id');
    }

    public function comments()
    {
        $workIds = $this->works()->pluck('_id')->toArray();

        return Comment::whereIn('work_id', $workIds);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BarberSchedule::class);
    }
}
