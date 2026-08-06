<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;

class BarbershopSetting extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'barbershop_setting';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Unica fila de configuracion, cacheada 60s (misma key/TTL que ya usaba
     * CheckMaintenanceMode). Se invalida sola en saved()/deleted(), asi que
     * los cambios del admin surten efecto de inmediato sin esperar el TTL.
     */
    public static function cached(): ?self
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => static::first());
    }

    protected $fillable = [
        'nombre',
        'logo',
        'direccion',
        'telefono',
        'horario_apertura',
        'horario_cierre',
        'politica_cancelacion',
        'redes_sociales',
        'datos_bancarios',
        'maintenance_mode',
    ];

    protected function casts(): array
    {
        return [
            'redes_sociales' => 'array',
            'datos_bancarios' => 'array',
            'maintenance_mode' => 'boolean',
        ];
    }
}
