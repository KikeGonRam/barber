<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', '+20 days');
        $hour = fake()->numberBetween(9, 19);
        $minute = fake()->randomElement([0, 15, 30, 45]);
        $duration = fake()->randomElement([20, 30, 45, 60]);
        $start = (clone $date)->setTime($hour, $minute, 0);
        $end = (clone $start)->modify('+'.$duration.' minutes');

        return [
            'client_id' => Client::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'fecha' => $start->format('Y-m-d'),
            'hora_inicio' => $start->format('H:i:s'),
            'hora_fin' => $end->format('H:i:s'),
            'estado' => fake()->randomElement(['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio']),
            'notas' => fake()->optional()->sentence(),
            'precio_cobrado' => fake()->optional(0.6)->randomFloat(2, 100, 650),
            'motivo_reagendamiento' => null,
            'cancelada_en' => null,
        ];
    }
}
