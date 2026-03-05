<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'tipo' => fake()->randomElement(['entrada', 'salida']),
            'cantidad' => fake()->numberBetween(1, 10),
            'motivo' => fake()->sentence(4),
            'appointment_id' => fake()->boolean(35) ? Appointment::factory() : null,
            'user_id' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
        ];
    }
}
