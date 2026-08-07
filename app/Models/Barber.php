<?php

namespace App\Models;

use App\Models\User as UserModel;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Perfil profesional de un barbero, vinculado 1-a-1 a un User (con rol
 * barbero). Guarda datos publicos de su pagina (especialidad, foto,
 * descripcion) y el slug usado en las URLs publicas via HasSlug.
 */
class Barber extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'user_id',
        'nombre',
        'especialidad',
        'especialidades',
        'telefono',
        'foto',
        'descripcion',
        'activo',
        'slug',
    ];

    // Fuente del slug: prioriza el nombre del User vinculado, luego 'nombre' propio.
    protected function slugSource(): string
    {
        $user = $this->user ?? ($this->user_id ? UserModel::find($this->user_id) : null);

        return $user?->name ?? $this->nombre ?? 'barbero';
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // Cuenta de usuario (login, rol) asociada a este perfil de barbero.
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }

    // Citas agendadas con este barbero.
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    // Trabajos/publicaciones del muro social (portafolio) de este barbero.
    public function works(): HasMany
    {
        return $this->hasMany(Work::class, 'barbero_id');
    }

    // No es una relacion Eloquent real: MongoDB no permite JOIN work->comment,
    // asi que primero se traen los ids de los works del barbero y luego se
    // filtran los comments por esos ids (2 queries en vez de 1 JOIN).
    public function comments()
    {
        $workIds = $this->works()->pluck('_id')->toArray();

        return Comment::whereIn('work_id', $workIds);
    }

    // Horarios de trabajo configurados por dia de la semana.
    public function schedules(): HasMany
    {
        return $this->hasMany(BarberSchedule::class);
    }
}
