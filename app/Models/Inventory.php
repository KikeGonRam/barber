<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'quantity',
        'min_stock',
        'price',
        'description',
        'active',
        'imagen',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_stock' => 'integer',
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_items')
            ->logFillable()
            ->logOnlyDirty();
    }
}
