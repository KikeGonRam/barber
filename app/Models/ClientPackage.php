<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Paquete prepagado YA COMPRADO por un cliente: instancia de ServicePackage
 * con su propio contador de usos restantes. `service_id` se guarda
 * denormalizado (copiado de ServicePackage al comprar) para que
 * PaymentService::create() pueda validar "este paquete es para el mismo
 * servicio de la cita" sin un join extra en el camino caliente de cobro.
 */
class ClientPackage extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_AGOTADO = 'agotado';

    public const ESTADO_EXPIRADO = 'expirado';

    protected $fillable = [
        'client_id',
        'service_package_id',
        'service_id',
        'usos_totales',
        'usos_restantes',
        'precio_pagado',
        'metodo_pago',
        'stripe_payment_id',
        'comprado_en',
        'expira_en',
        'estado',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'usos_totales' => 'integer',
            'usos_restantes' => 'integer',
            'precio_pagado' => 'decimal:2',
            'comprado_en' => 'datetime',
            'expira_en' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
