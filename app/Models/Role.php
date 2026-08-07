<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * Rol del paquete Spatie Permission, adaptado a MongoDB. Reimplementa
 * findById/findByName/findOrCreate y las relaciones permissions()/users()
 * porque el modelo base de Spatie asume SQL. Ver User::roleNames()/hasRole()
 * para el motivo por el que este proyecto no usa la relación users() directamente
 * en los checks de autorización (falla con MorphToMany en Mongo bajo carga eager).
 */
class Role extends Model implements RoleContract
{
    use HasPermissions, RefreshesPermissionCache;

    protected $connection = 'mongodb';

    protected $guarded = [];

    // Fuerza guard_name por defecto si no viene en los atributos (lo exige Spatie).
    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');
        parent::__construct($attributes);
        $this->setTable(config('permission.table_names.roles', 'roles'));
    }

    // Busca el rol por id + guard; lanza excepción si no existe.
    public static function findById(int|string $id, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $role = static::where((new static)->getKeyName(), $id)
            ->where('guard_name', $guardName)
            ->first();

        if (! $role) {
            throw RoleDoesNotExist::withId($id, $guardName);
        }

        return $role;
    }

    // Busca el rol por nombre + guard; lanza excepción si no existe.
    public static function findByName(string $name, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $role = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name, $guardName);
        }

        return $role;
    }

    // Busca el rol por nombre + guard, o lo crea si no existe.
    public static function findOrCreate(string $name, ?string $guardName = null): RoleContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $role = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $role) {
            $role = static::create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $role;
    }

    // Permisos asignados a este rol; si el paquete usa "teams", filtra por el team actual.
    public function permissions(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            config('permission.models.permission'),
            config('permission.table_names.role_has_permissions'),
            'role_id',
            'permission_id'
        );

        if (! app(PermissionRegistrar::class)->teams) {
            return $relation;
        }

        return $relation->wherePivot(app(PermissionRegistrar::class)->teamsKey, getPermissionsTeamId());
    }

    // Usuarios (del modelo del guard correspondiente) con este rol asignado. Ver nota de clase: no usar para checks de autorización.
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('permission.table_names.model_has_roles'),
            'role_id',
            config('permission.column_names.model_morph_key')
        );
    }
}
