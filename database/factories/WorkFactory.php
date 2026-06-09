<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    protected $model = Work::class;

    public function definition(): array
    {
        $titles = [
            'Fade clásico', 'Degradado suave', 'Barba estilo', 'Corte skin fade',
            'Desvanecido con textura', 'Corte clásico caballero', 'Pompadour',
            'Undercut', 'Buzz cut', 'Barba completa',
        ];

        return [
            'barbero_id'  => User::factory(),
            'title'       => fake()->randomElement($titles),
            'description' => fake()->sentence(8),
            'work_date'   => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
        ];
    }
}
