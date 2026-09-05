<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Producto de inventario: puede ser para venta al cliente o insumo interno
 * de trabajo. Soporta tipos "legacy" (venta/uso_interno) que se normalizan
 * a los nuevos (venta_cliente/insumo_trabajo) para compatibilidad con datos
 * antiguos. Usa soft deletes y Activitylog para auditoría.
 */
class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_SALE = 'venta_cliente';

    public const TYPE_SUPPLY = 'insumo_trabajo';

    public const LEGACY_TYPE_SALE = 'venta';

    public const LEGACY_TYPE_SUPPLY = 'uso_interno';

    public const SALE_TYPES = [
        self::TYPE_SALE,
        self::LEGACY_TYPE_SALE,
    ];

    public const SUPPLY_TYPES = [
        self::TYPE_SUPPLY,
        self::LEGACY_TYPE_SUPPLY,
    ];

    protected $attributes = [
        'activo' => true,
    ];

    // Ventana de gracia tras marcar un producto como "ya pedido": si sigue con
    // stock bajo pasado este plazo, vuelve a aparecer en la alerta diaria por
    // si el pedido nunca llegó o se olvidó dar seguimiento.
    public const RESTOCK_GRACE_DAYS = 7;

    protected $fillable = [
        'nombre',
        'categoria',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
        'tipo',
        'imagen',
        'activo',
        'reabastecimiento_pedido_en',
        'reabastecimiento_pedido_por',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
            'reabastecimiento_pedido_en' => 'datetime',
        ];
    }

    // Compara stock_actual contra stock_minimo (mismos criterios que el filtro de bajo stock en Mongo).
    public function isLowStock(): bool
    {
        return (int) $this->stock_actual <= (int) $this->stock_minimo;
    }

    // Si alguien marcó el producto como "ya pedido" hace menos de RESTOCK_GRACE_DAYS,
    // la alerta diaria/panel de bajo stock lo debe silenciar. Pasado ese plazo (pedido
    // nunca llegó o se olvidó), vuelve a contar como pendiente de atención.
    public function hasPendingRestockOrder(): bool
    {
        if (! $this->reabastecimiento_pedido_en) {
            return false;
        }

        return $this->reabastecimiento_pedido_en->diffInDays(now()) < self::RESTOCK_GRACE_DAYS;
    }

    // Activo por defecto si el campo no está presente en el documento (dato legado sin `activo`).
    public function isActive(): bool
    {
        return (bool) ($this->activo ?? true);
    }

    // Combina activo + tipo venta + stock disponible + precio válido para saber si se puede vender.
    public function isSellable(): bool
    {
        return $this->isActive()
            && in_array((string) $this->tipo, self::SALE_TYPES, true)
            && (int) $this->stock_actual > 0
            && (float) $this->precio_venta > 0;
    }

    // Scope con los mismos criterios de isSellable() pero como query, para listados.
    public function scopeAvailableForSale($query)
    {
        return $query
            ->where('activo', '!=', false)
            ->where('stock_actual', '>', 0)
            ->where('precio_venta', '>', 0)
            ->whereIn('tipo', self::SALE_TYPES);
    }

    // Traduce un tipo legado ('venta'/'uso_interno') al tipo actual equivalente.
    public static function normalizedType(?string $type): ?string
    {
        return match ($type) {
            self::LEGACY_TYPE_SALE => self::TYPE_SALE,
            self::LEGACY_TYPE_SUPPLY => self::TYPE_SUPPLY,
            default => $type,
        };
    }

    // Normaliza el payload de entrada (alias 'active'->'activo', tipo legado, decimales null) antes de guardar.
    public static function normalizePayload(array $payload): array
    {
        if (array_key_exists('active', $payload) && ! array_key_exists('activo', $payload)) {
            $payload['activo'] = $payload['active'];
        }

        unset($payload['active']);

        if (array_key_exists('tipo', $payload)) {
            $payload['tipo'] = self::normalizedType($payload['tipo']);
        }

        // El cast decimal:2 no admite null al escribir en MongoDB: omitir la
        // clave en vez de enviarla como null (create()/update() simplemente
        // no tocan el campo, igual que si no se hubiera enviado).
        foreach (['precio_compra', 'precio_venta'] as $decimalField) {
            if (array_key_exists($decimalField, $payload) && $payload[$decimalField] === null) {
                unset($payload[$decimalField]);
            }
        }

        // Las reglas de validación 'boolean'/'integer' de Laravel validan el
        // formato pero NO transforman el valor: un checkbox de formulario
        // llega como string "1"/"0", no como bool. MongoDB compara por tipo
        // BSON, así que guardar "1" en vez de true rompe silenciosamente
        // cualquier where('activo', true) posterior (alerta de stock bajo,
        // listado de productos activos, etc.) sin que create()/update() avisen.
        if (array_key_exists('activo', $payload) && $payload['activo'] !== null) {
            $payload['activo'] = filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['stock_actual', 'stock_minimo'] as $intField) {
            if (array_key_exists($intField, $payload) && $payload[$intField] !== null) {
                $payload[$intField] = (int) $payload[$intField];
            }
        }

        return $payload;
    }

    // Historial de entradas/salidas de este producto.
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Configura Activitylog: solo campos fillable y solo cuando cambian.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logFillable()
            ->logOnlyDirty();
    }
}
