<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Pedido de productos (tienda o add-on de una cita). Los items van embebidos.
 */
class Order extends Model
{
    protected $fillable = [
        'client_id',
        'folio',
        'items',          // [{product_id, nombre, precio, cantidad, subtotal}]
        'total',
        'estado',         // 'pendiente' | 'entregado' | 'cancelado'
        'tipo',           // 'tienda' | 'cita'
        'appointment_id', // si es add-on de una cita
        'metodo_pago',
        'entregado_en',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total' => 'decimal:2',
            'entregado_en' => 'datetime',
        ];
    }

    // Cliente que hizo el pedido.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Suma las cantidades de todos los items embebidos (no requiere JOIN, todo vive en el documento).
    public function itemsCount(): int
    {
        return collect($this->items ?? [])->sum('cantidad');
    }
}
