<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Token de autenticación para la app móvil, análogo a un personal access
 * token. Solo se guarda el hash (token_hash); el token en texto plano se
 * entrega una única vez al emitirlo y no puede recuperarse después.
 *
 * @property string $name
 * @property string|null $plataforma `web` o `movil`; null en tokens anteriores a este campo.
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 */
class MobileApiToken extends Model
{
    use HasFactory;

    public const PLATAFORMA_WEB = 'web';

    public const PLATAFORMA_MOVIL = 'movil';

    public const PLATAFORMAS = [self::PLATAFORMA_WEB, self::PLATAFORMA_MOVIL];

    /**
     * Nombres con los que los tokens web se emitían antes de que existiera el campo
     * `plataforma`: sirven para clasificar los tokens antiguos y los clientes que no
     * declaran su plataforma. Cualquier otro nombre (apps nativas) es móvil.
     */
    private const NOMBRES_WEB = ['Nuxt Web', 'Dashboard Web', 'Google OAuth', 'Google OAuth (spark)'];

    protected $fillable = [
        'user_id',
        'name',
        'plataforma',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    /** La plataforma declarada por el cliente o, si no declara ninguna, la que sugiere el nombre. */
    public static function resolvePlataforma(?string $declarada, ?string $nombre): string
    {
        if (in_array($declarada, self::PLATAFORMAS, true)) {
            return $declarada;
        }

        return in_array($nombre, self::NOMBRES_WEB, true) ? self::PLATAFORMA_WEB : self::PLATAFORMA_MOVIL;
    }

    /** Días de vigencia (deslizante) de un token de la plataforma; ver config/auth.php. */
    public static function ttlDays(string $plataforma): int
    {
        $porDefecto = $plataforma === self::PLATAFORMA_WEB ? 30 : 180;

        return max(1, (int) config("auth.api_token_ttl_days.{$plataforma}", $porDefecto));
    }

    public function plataformaEfectiva(): string
    {
        return self::resolvePlataforma($this->getAttribute('plataforma'), $this->getAttribute('name'));
    }

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // Usuario dueño del token.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
