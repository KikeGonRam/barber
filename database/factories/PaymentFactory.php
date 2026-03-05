<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'monto' => fake()->randomFloat(2, 100, 700),
            'metodo_pago' => fake()->randomElement(['efectivo', 'tarjeta', 'transferencia', 'qr']),
            'propina' => fake()->randomFloat(2, 0, 150),
            'comprobante_pdf' => null,
            'created_by' => User::factory(),
        ];
    }
}
