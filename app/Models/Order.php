<?php

namespace App\Models;

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
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total' => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function itemsCount(): int
    {
        return collect($this->items ?? [])->sum('cantidad');
    }
}
