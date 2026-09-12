<?php

namespace App\Models;

use App\Traits\HasPublicCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Tarjeta de regalo (roadmap P1): saldo prepagado de monto libre, no ligado
 * a ningún servicio en particular -- a diferencia de ClientPackage (N usos
 * de UN servicio), una gift card se puede aplicar a cualquier cobro hasta
 * agotar su saldo. Se redime por código (HasPublicCode), no por
 * "pertenecer" a un cliente: quien tenga el código puede usarla, igual que
 * una tarjeta de regalo física.
 */
class GiftCard extends Model
{
    use HasFactory, HasPublicCode;

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_AGOTADA = 'agotada';

    public const ESTADO_EXPIRADA = 'expirada';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'code',
        'monto_inicial',
        'saldo',
        'comprador_client_id',
        'comprador_nombre',
        'destinatario_email',
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
            'monto_inicial' => 'decimal:2',
            'saldo' => 'decimal:2',
            'comprado_en' => 'datetime',
            'expira_en' => 'datetime',
        ];
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'comprador_client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
