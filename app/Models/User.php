<?php

namespace App\Models;

use App\Notifications\EmailVerificationCodeNotification;
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

/**
 * Usuario de autenticación (cliente, barbero o admin). El rol real vive en
 * `role_id` (array de ids) dentro del propio documento en vez de la tabla
 * pivote MorphToMany que usa Spatie Permission por defecto: en MongoDB esa
 * relación falla o se comporta de forma inconsistente (ver hasRole()/
 * roleNames() más abajo), así que aquí se resuelve todo manualmente.
 * Puede tener un perfil de barbero (Barber) o de cliente (Client) via HasOne.
 */
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
        'email_verified_at',
        'expo_push_token',
        'notification_preferences',
        'verification_code',
        'verification_code_expires_at',
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
            'verification_code_expires_at' => 'datetime',
        ];
    }

    /**
     * Genera un codigo de 6 digitos (valido 10 min) y lo envia por correo con
     * la plantilla de marca, en vez del enlace firmado default de Laravel.
     */
    public function sendEmailVerificationNotification(): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->notify(new EmailVerificationCodeNotification($code));
    }

    // Configura Activitylog: solo registra cambios en name/email.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email'])
            ->logOnlyDirty();
    }

    // Notificaciones de base de datos del usuario, más recientes primero.
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    // Perfil de barbero, si este usuario tiene rol de barbero.
    public function barberProfile(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    // Perfil de cliente, si este usuario tiene rol de cliente.
    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    // Pagos que este usuario registró (no necesariamente los suyos propios).
    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    // Movimientos de inventario realizados por este usuario.
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Tokens de API móvil emitidos para este usuario.
    public function mobileApiTokens(): HasMany
    {
        return $this->hasMany(MobileApiToken::class);
    }

    // Genera un token nuevo en texto plano, guarda solo su hash y devuelve ambos (el plano no se puede recuperar después).
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

    // Preferencias de notificación resueltas con prioridad propias > legado del cliente > defaults.
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

    // Si el usuario tiene habilitado un canal de notificación dado.
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
    // Mismo nombre de método que Spatie pero resuelto desde role_id, no desde la relación roles().
    public function hasRole($roles, ?string $guard = null): bool
    {
        $requiredRoles = $this->normalizeRoleNames($roles, $guard);

        if ($requiredRoles->isEmpty()) {
            return false;
        }

        return $this->roleNames($guard)->intersect($requiredRoles)->isNotEmpty();
    }

    // Verdadero si el usuario tiene al menos uno de los roles dados.
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    // Verdadero solo si el usuario tiene todos los roles dados.
    public function hasAllRoles($roles, ?string $guard = null): bool
    {
        $requiredRoles = $this->normalizeRoleNames($roles, $guard)->unique()->values();

        if ($requiredRoles->isEmpty()) {
            return false;
        }

        return $requiredRoles->diff($this->roleNames($guard))->isEmpty();
    }

    // Alias compatible con la API de Spatie.
    public function getRoleNames(): Collection
    {
        return $this->roleNames();
    }

    // Todos los permisos heredados por los roles del usuario, deduplicados y ordenados por nombre.
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
    // Normaliza role_id (puede faltar, ser escalar o array) a un array de ids como string.
    private function roleIds(): array
    {
        return collect((array) ($this->role_id ?? []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    // Aplana cualquier combinación de string(s) separados por '|', arrays o Collections a nombres de rol.
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

    // Convierte un valor individual (enum, modelo Role, id o nombre) a su(s) nombre(s) de rol.
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

    // Teléfono desde el perfil de cliente (puede ser null si no tiene uno).
    public function clientPhone(): ?string
    {
        return $this->clientProfile?->telefono;
    }

    // Comentarios hechos por este usuario en el portafolio.
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Reacciones hechas por este usuario en el portafolio.
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    // Trabajos guardados/favoritos por este usuario.
    public function savedWorks(): HasMany
    {
        return $this->hasMany(SavedWork::class);
    }
}
