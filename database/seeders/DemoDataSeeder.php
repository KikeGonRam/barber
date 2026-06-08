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
        $receptionUser = User::firstOrCreate([
            'email' => 'recepcion@example.com',
        ], [
            'name' => 'Recepcionista Demo',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        
        if (!$receptionUser->hasRole('recepcionista')) {
            $receptionUser->assignRole('recepcionista');
        }

        $barbers = Barber::all();
        if ($barbers->isEmpty()) {
            $barbers = Barber::factory()->count(4)->create();
            foreach ($barbers as $barber) {
                if (!$barber->user->hasRole('barbero')) {
                    $barber->user->assignRole('barbero');
                }

                // Initialize schedule for each barber
                for ($i = 1; $i <= 6; $i++) {
                    $barber->schedules()->updateOrCreate([
                        'day_of_week' => $i,
                    ], [
                        'start_time' => '09:00:00',
                        'end_time' => '21:00:00',
                        'is_working' => true,
                    ]);
                }
                // Sunday off
                $barber->schedules()->updateOrCreate([
                    'day_of_week' => 0,
                ], [
                    'is_working' => false,
                ]);
            }
        }

        $clients = Client::factory()->count(20)->create();
        foreach ($clients as $client) {
            if (!$client->user->hasRole('cliente')) {
                $client->user->assignRole('cliente');
            }
        }

        $services = Service::count() > 0 ? Service::all() : Service::factory()->count(12)->create();
        $products = Product::count() > 0 ? Product::all() : Product::factory()->count(18)->create();

        $appointments = collect();

        // Create data for the last 30 days
        foreach (range(0, 30) as $daysAgo) {
            $date = now()->subDays($daysAgo);
            $numAppointments = rand(5, 15);

            for ($i = 0; $i < $numAppointments; $i++) {
                $client = $clients->random();
                $barber = $barbers->random();
                $service = $services->random();

                $createdAt = $date->copy()->setHour(rand(9, 20))->setMinute(rand(0, 59));
                $startTime = $createdAt->format('H:i:s');
                $endTime = $createdAt->copy()->addMinutes(30)->format('H:i:s');
                
                $appointment = Appointment::factory()->create([
                    'client_id' => $client->id,
                    'barber_id' => $barber->id,
                    'service_id' => $service->id,
                    'fecha' => $date->format('Y-m-d'),
                    'hora_inicio' => $startTime,
                    'hora_fin' => $endTime,
                    'estado' => $daysAgo > 0 ? 
                        fake()->randomElement(['completada', 'completada', 'completada', 'cancelada', 'no_asistio']) : 
                        fake()->randomElement(['pendiente', 'confirmada', 'completada']),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                if ($appointment->estado === 'completada') {
                    Payment::factory()->create([
                        'appointment_id' => $appointment->id,
                        'created_by' => $receptionUser->id,
                        'monto' => $appointment->precio_cobrado ?: fake()->randomFloat(2, 120, 650),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                $appointments->push($appointment);
            }
        }

        foreach (range(1, 150) as $index) {
            $product = $products->random();
            $type = fake()->randomElement(['entrada', 'salida']);
            $quantity = fake()->numberBetween(1, 10);
            $date = now()->subDays(rand(0, 30));

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
                'appointment_id' => fake()->boolean(30) ? $appointments->random()->id : null,
                'user_id' => fake()->randomElement([$receptionUser->id, $barbers->random()->user_id]),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
