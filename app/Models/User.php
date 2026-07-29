<?php

namespace App\Models;

use BackedEnum;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'expo_push_token',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email'])
            ->logOnlyDirty();
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    public function barberProfile(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function mobileApiTokens(): HasMany
    {
        return $this->hasMany(MobileApiToken::class);
    }

    public function issueMobileApiToken(string $name = 'Mobile App', ?array $abilities = null, ?Carbon $expiresAt = null): array
    {
        $plainToken = bin2hex(random_bytes(32));

        $token = $this->mobileApiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => $abilities,
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'token_model' => $token,
        ];
    }

    public function notificationPreferences(): array
    {
        $defaults = [
            'in_app' => true,
            'email' => true,
            'sms' => false,
            'whatsapp' => false,
            'promociones' => true,
        ];

        // Prioridad: propias (User) > legado del perfil de cliente > defaults.
        $legacy = is_array($this->clientProfile?->preferencias_notificacion)
            ? $this->clientProfile->preferencias_notificacion
            : [];
        $own = is_array($this->notification_preferences)
            ? $this->notification_preferences
            : [];

        return array_merge($defaults, $legacy, $own);
    }

    public function wantsNotificationChannel(string $channel): bool
    {
        return (bool) ($this->notificationPreferences()[$channel] ?? false);
    }

    /**
     * Telefono destino para SMS/WhatsApp (canal Twilio).
     */
    public function routeNotificationForTwilio(): ?string
    {
        return $this->clientPhone();
    }

    /**
     * Filtra usuarios por nombre(s) de rol de forma nativa en MongoDB.
     *
     * El scope role() de Spatie usa MorphToMany y falla en Mongo
     * ("MorphToMany is not supported for hybrid query constraints"); aqui los
     * roles se guardan como role_id en el propio documento del usuario.
     *
     * @param  array<int, string>|string  $names
     */
    public function scopeWhereRoleName($query, array|string $names)
    {
        $roleIds = Role::whereIn('name', (array) $names)->pluck('id')->all();

        return $query->whereIn('role_id', $roleIds);
    }

    /**
     * Nombres de rol de este usuario, resueltos directamente desde `role_id`.
     *
     * hasRole()/hasAnyRole() de Spatie dependen de la relación roles()
     * (MorphToMany), que en Mongo solo funciona cuando se accede de forma
     * "lazy" (propiedad magica `$user->roles`) pero devuelve vacio cuando se
     * carga via loadMissing()/load() -- justo lo que hasRole() hace
     * internamente. Eso provoca falsos "sin rol" intermitentes segun si algo
     * mas ya habia tocado la relacion antes en el request. Usar este metodo
     * (o hasRoleName()) en vez de hasRole() para checks confiables.
     */
    public function roleNames(?string $guard = null): Collection
    {
        $roleIds = $this->roleIds();

        if ($roleIds === []) {
            return collect();
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->when($guard, fn ($query) => $query->where('guard_name', $guard))
            ->pluck('name')
            ->values();
    }

    public function hasRoleName(string $name): bool
    {
        return $this->roleNames()->contains($name);
    }

    /**
     * Mantiene estable la API pública de Spatie, pero resuelve los roles desde
     * `role_id`, que es la fuente real de este proyecto en MongoDB.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        $requiredRoles = $this->normalizeRoleNames($roles, $guard);

        if ($requiredRoles->isEmpty()) {
            return false;
        }

        return $this->roleNames($guard)->intersect($requiredRoles)->isNotEmpty();
    }

    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasAllRoles($roles, ?string $guard = null): bool
    {
        $requiredRoles = $this->normalizeRoleNames($roles, $guard)->unique()->values();

        if ($requiredRoles->isEmpty()) {
            return false;
        }

        return $requiredRoles->diff($this->roleNames($guard))->isEmpty();
    }

    public function getRoleNames(): Collection
    {
        return $this->roleNames();
    }

    public function getPermissionsViaRoles(): Collection
    {
        $roleIds = $this->roleIds();

        if ($roleIds === []) {
            return collect();
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique(fn ($permission) => (string) $permission->getKey())
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function roleIds(): array
    {
        return collect((array) ($this->role_id ?? []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function normalizeRoleNames(mixed $roles, ?string $guard = null): Collection
    {
        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = explode('|', $roles);
        }

        return collect(is_array($roles) || $roles instanceof Collection ? $roles : [$roles])
            ->flatten()
            ->flatMap(fn ($role) => $this->roleNameFromValue($role, $guard))
            ->filter()
            ->values();
    }

    private function roleNameFromValue(mixed $role, ?string $guard = null): array
    {
        if ($role instanceof BackedEnum) {
            $role = $role->value;
        }

        if ($role instanceof RoleContract) {
            return [$role->name];
        }

        if (is_string($role) && str_contains($role, '|')) {
            return explode('|', $role);
        }

        if (is_int($role) || (is_string($role) && PermissionRegistrar::isUid($role))) {
            return Role::query()
                ->where('id', $role)
                ->when($guard, fn ($query) => $query->where('guard_name', $guard))
                ->pluck('name')
                ->all();
        }

        return is_string($role) ? [$role] : [];
    }

    public function clientPhone(): ?string
    {
        return $this->clientProfile?->telefono;
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function savedWorks(): HasMany
    {
        return $this->hasMany(SavedWork::class);
    }
}
