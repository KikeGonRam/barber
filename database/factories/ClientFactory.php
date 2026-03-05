<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'telefono' => fake()->numerify('+52##########'),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-16 years')->format('Y-m-d'),
            'preferencias_notificacion' => [
                'in_app' => true,
                'email' => true,
                'sms' => false,
                'whatsapp' => false,
            ],
        ];
    }
}
