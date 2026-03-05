<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Corte clásico', 'Corte fade', 'Arreglo de barba', 'Combo corte y barba', 'Tratamiento capilar']).' '.fake()->numberBetween(1, 99),
            'categoria' => fake()->randomElement(['corte', 'barba', 'combo', 'tratamiento']),
            'precio' => fake()->randomFloat(2, 100, 650),
            'duracion_min' => fake()->randomElement([20, 30, 45, 60, 75]),
            'imagen' => null,
            'descripcion' => fake()->sentence(10),
            'activo' => fake()->boolean(85),
        ];
    }
}
