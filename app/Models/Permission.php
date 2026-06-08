<?php

namespace App\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\RefreshesPermissionCache;

class Permission extends Model implements PermissionContract
{
    use HasPermissions, RefreshesPermissionCache;

    protected $connection = 'mongodb';
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');
        parent::__construct($attributes);
        $this->setTable(config('permission.table_names.permissions', 'permissions'));
    }

    public static function findById(int|string $id, string|null $guardName = null): PermissionContract
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

    public static function findByName(string $name, string|null $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $permission = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw PermissionDoesNotExist::named($name, $guardName);
        }

        return $permission;
    }

    public static function findOrCreate(string $name, string|null $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $permission = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            $permission = static::create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    public function hasPermissionTo(string|BackedEnum $permission, string|null $guardName = null): bool
    {
        if ($this->getWildcardClass()) {
            return $this->getWildcardClass()::check($this, $permission, $guardName);
        }

        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass::findByName($permission, $guardName ?? $this->getDefaultGuardName());
        }

        if ($permission instanceof BackedEnum) {
            $permission = $permissionClass::findByName($permission->value, $guardName ?? $this->getDefaultGuardName());
        }

        return $this->permissions->contains($permission->getKeyName(), $permission->getKey());
    }

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
