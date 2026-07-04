<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Siembra ÚNICAMENTE la colección `appointments` — genera un historial de
 * 10 a 15 citas por cliente (88% completadas, resto canceladas/confirmadas/
 * pendientes según antigüedad), distribuidas en los últimos 18 meses.
 *
 * No crea pagos ni puntos de lealtad — eso lo hacen PaymentSeeder y
 * LoyaltyTransactionSeeder, leyendo las citas completadas que aquí se crean.
 *
 * Ejecutar DESPUÉS de ClientSeeder, BarberSeeder y ServiceSeeder.
 */
class AppointmentSeeder extends Seeder
{
    private array $usedCodes = [];

    public function run(): void
    {
        $clients   = Client::all(['_id']);
        $barberIds = Barber::where('activo', true)->pluck('id')->values()->toArray();
        $services  = Service::where('activo', true)->get(['_id', 'precio', 'duracion_min']);

        if ($clients->isEmpty() || empty($barberIds) || $services->isEmpty()) {
            $this->command->error('AppointmentSeeder: faltan datos base. Ejecuta ClientSeeder, BarberSeeder y ServiceSeeder primero.');
            return;
        }

        $this->command->info(
            "  Clientes: {$clients->count()} | Barberos: ".count($barberIds)." | Servicios: {$services->count()}"
        );

        $total    = 0;
        $chunkNum = 0;

        foreach ($clients->chunk(100) as $batch) {
            DB::reconnect();
            $chunkNum++;
            $docs = [];

            foreach ($batch as $client) {
                $clientId = (string) $client->_id;
                $numAppts = rand(10, 15);

                for ($i = 0; $i < $numAppts; $i++) {
                    $service   = $services->random();
                    $barberId  = $barberIds[array_rand($barberIds)];
                    $fecha     = $this->randomPastDate();
                    $hora      = $this->randomHora();
                    $horaFin   = $this->calcHoraFin($hora, (int) $service->duracion_min);
                    $precio    = (float) $service->precio;
                    $estado    = $this->isCompleted() ? 'completada' : $this->cancelEstado($fecha);
                    $ts        = new UTCDateTime($fecha->timestamp * 1000);
                    $fechaTs   = new UTCDateTime(Carbon::parse($fecha->format('Y-m-d'))->timestamp * 1000);

                    $docs[] = [
                        '_id'            => new ObjectId(),
                        'client_id'      => $clientId,
                        'barber_id'      => $barberId,
                        'service_id'     => (string) $service->_id,
                        'fecha'          => $fechaTs,
                        'hora_inicio'    => $hora,
                        'hora_fin'       => $horaFin,
                        'estado'         => $estado,
                        'precio_cobrado' => $precio,
                        'metodo_pago'    => 'efectivo',
                        'notas'          => null,
                        'code'           => $this->uniqueCode(),
                        'deleted_at'     => null,
                        'created_at'     => $ts,
                        'updated_at'     => $ts,
                    ];
                }
            }

            if ($docs) {
                Appointment::raw(fn ($col) => $col->insertMany($docs));
                $total += count($docs);
            }

            $this->command->info("  Lote {$chunkNum}/10 — {$total} citas");
        }

        $this->command->info("  ✓ {$total} citas creadas");
    }

    private function randomPastDate(): Carbon
    {
        // Historial de hasta 18 meses atrás (mínimo 8 días)
        $date = Carbon::now()->subDays(rand(8, 548));

        // La barbería no abre los domingos
        while ($date->dayOfWeek === Carbon::SUNDAY) {
            $date->subDay();
        }

        return $date;
    }

    private function randomHora(): string
    {
        $hour    = rand(9, 19);
        $minutes = [0, 0, 0, 15, 30, 45]; // :00 más frecuente
        return sprintf('%02d:%02d', $hour, $minutes[array_rand($minutes)]);
    }

    private function calcHoraFin(string $inicio, int $durMin): string
    {
        [$h, $m]  = explode(':', $inicio);
        $totalMin = (int) $h * 60 + (int) $m + $durMin;
        return sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
    }

    private function isCompleted(): bool
    {
        return rand(1, 100) <= 88;
    }

    private function cancelEstado(Carbon $fecha): string
    {
        // Citas muy recientes (< 15 días) pueden estar confirmadas o pendientes
        if (Carbon::now()->diffInDays($fecha) <= 15 && rand(1, 3) === 1) {
            return rand(0, 1) ? 'confirmada' : 'pendiente';
        }
        return 'cancelada';
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (isset($this->usedCodes[$code]));

        $this->usedCodes[$code] = true;
        return $code;
    }
}
