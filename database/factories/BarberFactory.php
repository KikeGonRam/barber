<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barber>
 */
class BarberFactory extends Factory
{
    protected $model = Barber::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'especialidades' => fake()->randomElement(['Fade, Barba', 'Corte clásico', 'Tratamientos capilares']),
            'foto' => null,
            'descripcion' => fake()->sentence(),
            'activo' => true,
        ];
    }
}
