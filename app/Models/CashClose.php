<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Corte de caja de un día: cuánto dinero esperaba el sistema por método de
 * pago, cuánto efectivo contó físicamente quien cierra, y la diferencia.
 *
 * "esperado" se guarda como snapshot a propósito, no se recalcula al leer:
 * el corte es un documento contable de lo que se sabía al cerrar. Si después
 * se aprueba una transferencia con fecha de ese día, el histórico no debe
 * cambiar solo por eso -- se vería como una diferencia en el corte siguiente,
 * que es justo lo que un arqueo tiene que dejar visible.
 */
class CashClose extends Model
{
    use HasFactory;

    protected $collection = 'cash_closes';

    protected $fillable = [
        'fecha',
        'esperado',          // ['efectivo' => 0.0, 'tarjeta' => 0.0, ...]
        'esperado_total',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',        // contado - esperado en efectivo
        'notas',
        'cerrado_por',
        'cerrado_por_nombre',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'esperado' => 'array',
            'esperado_total' => 'float',
            'efectivo_esperado' => 'float',
            'efectivo_contado' => 'float',
            'diferencia' => 'float',
        ];
    }
}
