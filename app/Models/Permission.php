<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * Permiso individual del paquete Spatie Permission, adaptado a MongoDB.
 * Reimplementa findById/findByName/findOrCreate y las relaciones roles()/
 * users() porque el modelo base de Spatie asume SQL; aquí corren sobre
 * colecciones Mongo vía MongoDB\Laravel\Eloquent\Model.
 */
class Permission extends Model implements PermissionContract
{
    use HasPermissions, RefreshesPermissionCache;

    protected $connection = 'mongodb';

    protected $guarded = [];

    // Fuerza guard_name por defecto si no viene en los atributos (lo exige Spatie).
    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');
        parent::__construct($attributes);
        $this->setTable(config('permission.table_names.permissions', 'permissions'));
    }

    // Busca el permiso por id + guard; lanza excepción si no existe.
    public static function findById(int|string $id, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $permission = static::where((new static)->getKeyName(), $id)
            ->where('guard_name', $guardName)
            ->first();

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    // Busca el permiso por nombre + guard; lanza excepción si no existe.
    public static function findByName(string $name, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $permission = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    // Busca el permiso por nombre + guard, o lo crea si no existe.
    public static function findOrCreate(string $name, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $permission = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            $permission = static::create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    // Roles que incluyen este permiso; si el paquete usa "teams", filtra por el team actual.
    public function roles(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.role_has_permissions'),
            'permission_id',
            'role_id'
        );

        if (! app(PermissionRegistrar::class)->teams) {
            return $relation;
        }

        return $relation->wherePivot(app(PermissionRegistrar::class)->teamsKey, getPermissionsTeamId());
    }

    // Usuarios (del modelo del guard correspondiente) con este permiso asignado directamente.
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('permission.table_names.model_has_permissions'),
            'permission_id',
            config('permission.column_names.model_morph_key')
        );
    }
}
