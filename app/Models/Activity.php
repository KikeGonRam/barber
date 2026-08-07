<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Registro de auditoria/bitacora del paquete Spatie Activitylog, persistido
 * en la coleccion `activity_log` de MongoDB.
 *
 * Es schemaless (usa $guarded = [] en vez de $fillable): Spatie escribe
 * las columnas que necesite segun el evento (log_name, event, subject_type,
 * subject_id, causer_type, causer_id, properties, batch_uuid, etc.) sin que
 * el modelo las declare explicitamente.
 *
 * `subject` y `causer` son relaciones polimorficas (MorphTo): subject es la
 * entidad afectada (ej. un Appointment) y causer quien hizo la accion
 * (normalmente un User). Al no haber JOINs en MongoDB, cada acceso resuelve
 * subject_type/subject_id por separado.
 */
class Activity extends Model implements ActivityContract
{
    protected $connection = 'mongodb';

    protected $collection = 'activity_log';

    protected $guarded = [];

    protected $casts = [
        // 'properties' guarda el payload del evento (attributes/old) como
        // Collection en vez de array plano, para poder usar metodos de
        // Collection (only(), filter()) directamente sobre el.
        'properties' => 'collection',
    ];

    // Entidad afectada por el evento (ej. el Appointment que cambio).
    public function subject(): MorphTo
    {
        if (config('activitylog.subject_returns_soft_deleted_models')) {
            return $this->morphTo()->withTrashed();
        }

        return $this->morphTo();
    }

    // Quien causo el evento (normalmente el User autenticado).
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    // Lee un valor arbitrario dentro de 'properties' usando dot notation.
    public function getProperty(string $propertyName, mixed $defaultValue = null): mixed
    {
        $props = $this->properties instanceof Collection
            ? $this->properties->toArray()
            : (array) ($this->properties ?? []);

        return Arr::get($props, $propertyName, $defaultValue);
    }

    // Alias de getProperty(), compatibilidad con la API de Spatie Activitylog.
    public function getExtraProperty(string $propertyName, mixed $defaultValue = null): mixed
    {
        return $this->getProperty($propertyName, $defaultValue);
    }

    // Extrae solo 'attributes' (valores nuevos) y 'old' (valores previos) del log de cambios.
    public function changes(): Collection
    {
        if (! $this->properties instanceof Collection) {
            return new Collection;
        }

        return collect(array_filter($this->properties->only(['attributes', 'old'])->toArray()));
    }

    // Scope: filtra por uno o varios log_name (acepta variadic o un array como primer argumento).
    public function scopeInLog(Builder $query, ...$logNames): Builder
    {
        if (is_array($logNames[0] ?? null)) {
            $logNames = $logNames[0];
        }

        return $query->whereIn('log_name', $logNames);
    }

    // Scope: filtra actividades cuyo subject sea el modelo dado (por tipo + id).
    public function scopeForSubject(Builder $query, EloquentModel $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    // Scope: filtra actividades cuyo causer sea el modelo dado (por tipo + id).
    public function scopeForCauser(Builder $query, EloquentModel $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    // Alias de scopeForCauser(), nombre usado por la API publica de Spatie.
    public function scopeCausedBy(Builder $query, EloquentModel $causer): Builder
    {
        return $this->scopeForCauser($query, $causer);
    }

    // Scope: filtra por tipo de evento (created|updated|deleted, etc.).
    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    // Scope: filtra actividades que pertenecen al mismo batch (operaciones agrupadas).
    public function scopeForBatch(Builder $query, string $batchUuid): Builder
    {
        return $query->where('batch_uuid', $batchUuid);
    }
}
