<?php

namespace Database\Factories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'quantity' => $this->faker->numberBetween(0, 100),
            'min_stock' => $this->faker->numberBetween(0, 10),
            'price' => $this->faker->randomFloat(2, 1, 1000),
            'description' => $this->faker->sentence(),
            'active' => true,
        ];
    }
}
