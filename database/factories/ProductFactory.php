<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $stock = fake()->numberBetween(0, 80);
        $minimum = fake()->numberBetween(3, 20);

        return [
            'nombre' => fake()->randomElement(['Pomada', 'Cera', 'Shampoo', 'Navajas', 'Toallas', 'Loción']).' '.fake()->numberBetween(1, 99),
            'categoria' => fake()->randomElement(['venta', 'insumo']),
            'descripcion' => fake()->sentence(),
            'precio_compra' => fake()->randomFloat(2, 10, 300),
            'precio_venta' => fake()->randomFloat(2, 20, 500),
            'stock_actual' => $stock,
            'stock_minimo' => $minimum,
            'tipo' => fake()->randomElement(['venta_cliente', 'insumo_trabajo']),
        ];
    }
}
