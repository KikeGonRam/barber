<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $receptionUser = User::factory()->create([
            'name' => 'Recepcionista Demo',
            'email' => 'recepcion@example.com',
            'password' => 'password',
        ]);
        $receptionUser->assignRole('recepcionista');

        $barbers = Barber::factory()->count(4)->create();
        foreach ($barbers as $barber) {
            $barber->user->assignRole('barbero');

            // Initialize schedule for each barber
            for ($i = 1; $i <= 6; $i++) {
                $barber->schedules()->create([
                    'day_of_week' => $i,
                    'start_time' => '09:00:00',
                    'end_time' => '21:00:00',
                    'is_working' => true,
                ]);
            }
            // Sunday off
            $barber->schedules()->create([
                'day_of_week' => 0,
                'is_working' => false,
            ]);
        }

        $clients = Client::factory()->count(20)->create();
        foreach ($clients as $client) {
            $client->user->assignRole('cliente');
        }

        $services = Service::factory()->count(12)->create();
        $products = Product::factory()->count(18)->create();

        $appointments = collect();

        foreach (range(1, 80) as $index) {
            $client = $clients->random();
            $barber = $barbers->random();
            $service = $services->random();

            $appointment = Appointment::factory()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'estado' => fake()->randomElement(['pendiente', 'confirmada', 'completada', 'cancelada', 'no_asistio']),
            ]);

            if ($appointment->estado === 'completada') {
                Payment::factory()->create([
                    'appointment_id' => $appointment->id,
                    'created_by' => $receptionUser->id,
                    'monto' => $appointment->precio_cobrado ?: fake()->randomFloat(2, 120, 650),
                ]);
            }

            $appointments->push($appointment);
        }

        foreach (range(1, 120) as $index) {
            $product = $products->random();
            $type = fake()->randomElement(['entrada', 'salida']);
            $quantity = fake()->numberBetween(1, 5);

            if ($type === 'salida' && $product->stock_actual < $quantity) {
                $type = 'entrada';
            }

            if ($type === 'entrada') {
                $product->increment('stock_actual', $quantity);
            } else {
                $product->decrement('stock_actual', $quantity);
            }

            InventoryMovement::factory()->create([
                'product_id' => $product->id,
                'tipo' => $type,
                'cantidad' => $quantity,
                'appointment_id' => fake()->boolean(45) ? $appointments->random()->id : null,
                'user_id' => fake()->randomElement([$receptionUser->id, $barbers->random()->user_id]),
            ]);
        }
    }
}
