<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return (bool) ($this->activo ?? true);
    }

    public function isSellable(): bool
    {
        return $this->isActive()
            && in_array((string) $this->tipo, self::SALE_TYPES, true)
            && (int) $this->stock_actual > 0
            && (float) $this->precio_venta > 0;
    }

    public function scopeAvailableForSale($query)
    {
        return $query
            ->where('activo', '!=', false)
            ->where('stock_actual', '>', 0)
            ->where('precio_venta', '>', 0)
            ->whereIn('tipo', self::SALE_TYPES);
    }

    public static function normalizedType(?string $type): ?string
    {
        return match ($type) {
            self::LEGACY_TYPE_SALE => self::TYPE_SALE,
            self::LEGACY_TYPE_SUPPLY => self::TYPE_SUPPLY,
            default => $type,
        };
    }

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

        return $payload;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logFillable()
            ->logOnlyDirty();
    }
}
