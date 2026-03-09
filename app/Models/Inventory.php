<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;
    
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
}
