<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MassiveDataSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');
        DB::connection()->disableQueryLog();
        
        $receptionUser = User::where('email', 'recepcion@example.com')->first();
        if (!$receptionUser) {
            $receptionUser = User::factory()->create([
                'name' => 'Recepcionista Demo',
                'email' => 'recepcion@example.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $receptionUser->assignRole('recepcionista');
        }

        $barbers = Barber::pluck('id')->toArray();
        $clients = Client::pluck('id')->toArray();
        $services = Service::pluck('id')->toArray();

        if (empty($barbers) || empty($clients) || empty($services)) {
            $this->command->error('Faltan datos base (Barberos, Clientes o Servicios). Ejecuta DemoDataSeeder primero.');
            return;
        }

        $totalRecords = 100000;
        $batchSize = 2000;
        $startDate = Carbon::create(2023, 1, 1);
        $endDate = Carbon::now();
        $totalDays = $startDate->diffInDays($endDate);

        $this->command->info("Generando $totalRecords registros desde 2023 hasta hoy...");

        $currentAppointments = DB::table('appointments')->count();
        if ($currentAppointments < $totalRecords) {
            $appointments = [];
            for ($i = $currentAppointments + 1; $i <= $totalRecords; $i++) {
                $daysToAdd = rand(0, $totalDays);
                $date = $startDate->copy()->addDays($daysToAdd);
                $hour = rand(9, 19);
                $minute = rand(0, 59);
                $dateTimeStr = $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $hour, $minute);
                
                $status = 'completada';
                if ($date->isFuture()) {
                    $status = 'pendiente';
                } elseif (rand(1, 10) > 8) {
                    $status = rand(1, 2) == 1 ? 'cancelada' : 'no_asistio';
                }

                $appointments[] = [
                    'client_id' => $clients[array_rand($clients)],
                    'barber_id' => $barbers[array_rand($barbers)],
                    'service_id' => $services[array_rand($services)],
                    'fecha' => $date->format('Y-m-d'),
                    'hora_inicio' => sprintf('%02d:%02d:00', $hour, $minute),
                    'hora_fin' => sprintf('%02d:%02d:00', $hour, $minute + 30 > 59 ? 59 : $minute + 30),
                    'estado' => $status,
                    'notas' => 'Cita masiva generada para pruebas.',
                    'precio_cobrado' => rand(200, 800),
                    'created_at' => $dateTimeStr,
                    'updated_at' => $dateTimeStr,
                ];

                if ($i % $batchSize == 0) {
                    DB::table('appointments')->insert($appointments);
                    $appointments = [];
                    $this->command->comment("Insertados $i registros de citas...");
                    gc_collect_cycles();
                }
            }
            if (!empty($appointments)) {
                DB::table('appointments')->insert($appointments);
            }
        } else {
            $this->command->info('Ya existen suficientes citas, saltando generación.');
        }

        $this->command->info('Generando pagos para las citas completadas...');
        
        $paymentBatches = [];
        $query = DB::table('appointments')
            ->where('estado', 'completada')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('payments')
                      ->whereRaw('payments.appointment_id = appointments.id');
            })
            ->select(['id', 'precio_cobrado', 'created_at'])
            ->orderBy('id');

        foreach ($query->cursor() as $app) {
            $paymentBatches[] = [
                'appointment_id' => $app->id,
                'monto' => $app->precio_cobrado,
                'metodo_pago' => 'efectivo',
                'created_by' => $receptionUser->id,
                'created_at' => $app->created_at,
                'updated_at' => $app->created_at,
            ];

            if (count($paymentBatches) >= $batchSize) {
                DB::table('payments')->insert($paymentBatches);
                $paymentBatches = [];
                gc_collect_cycles();
            }
        }
        if (!empty($paymentBatches)) {
            DB::table('payments')->insert($paymentBatches);
        }

        $this->command->info('Generando movimientos de inventario...');
        $products = DB::table('products')->pluck('id')->toArray();
        if (!empty($products)) {
            $movements = [];
            for ($j = 1; $j <= 50000; $j++) {
                $date = $startDate->copy()->addDays(rand(0, $totalDays));
                $movements[] = [
                    'product_id' => $products[array_rand($products)],
                    'tipo' => rand(1, 10) > 3 ? 'salida' : 'entrada',
                    'cantidad' => rand(1, 5),
                    'user_id' => $receptionUser->id,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];

                if ($j % $batchSize == 0) {
                    DB::table('inventory_movements')->insert($movements);
                    $movements = [];
                }
            }
        }

        $this->command->info('Proceso masivo completado.');
    }
}
