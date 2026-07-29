<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AppointmentSeeder extends Seeder
{
    private const MAX_PER_CLIENT = 150;

    private const METODOS = ['efectivo', 'tarjeta', 'transferencia'];

    /** Distribucion de estados terminales para citas en el pasado. */
    private const PAST_STATES = [
        'completada' => 72,
        'cancelada' => 16,
        'no_asistio' => 12,
    ];

    public function run(): void
    {
        $clientIds = Client::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $barberIds = Barber::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $services = Service::query()->get(['_id', 'precio', 'duracion_min'])
            ->map(fn ($s) => ['id' => (string) $s->id, 'precio' => (float) $s->precio, 'duracion' => (int) $s->duracion_min])
            ->all();

        if (empty($clientIds) || empty($barberIds) || empty($services)) {
            $this->command->warn('Faltan clientes, barberos o servicios; se omite AppointmentSeeder.');

            return;
        }

        $today = Carbon::today();
        $weekEnd = Carbon::today()->endOfWeek(Carbon::SUNDAY);
        $start = Carbon::create(2024, 1, 1);
        $usedCodes = [];

        $rows = [];
        $totalCreated = 0;
        $now = now();

        foreach ($clientIds as $clientId) {
            $n = random_int(0, self::MAX_PER_CLIENT);

            for ($i = 0; $i < $n; $i++) {
                $fecha = $this->randomBusinessDate($start, $weekEnd);
                $isFuture = $fecha->greaterThanOrEqualTo($today);
                $service = $services[array_rand($services)];
                $barberId = $barberIds[array_rand($barberIds)];

                [$horaInicio, $horaFin] = $this->randomSlot($service['duracion']);

                if ($isFuture) {
                    $estado = 'pendiente';
                    $precioCobrado = null;
                    $metodoPago = null;
                } else {
                    $estado = $this->weightedState();
                    $precioCobrado = $estado === 'completada' ? $service['precio'] : null;
                    $metodoPago = $estado === 'completada' ? self::METODOS[array_rand(self::METODOS)] : null;
                }

                $code = $this->uniqueCode($usedCodes);
                $createdAt = $isFuture ? $now->copy() : $fecha->copy()->addHours(random_int(8, 20));

                $rows[] = [
                    'client_id' => $clientId,
                    'barber_id' => $barberId,
                    'service_id' => $service['id'],
                    'fecha' => $fecha->copy(),
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'estado' => $estado,
                    'metodo_pago' => $metodoPago,
                    'precio_cobrado' => $precioCobrado,
                    'code' => $code,
                    'cancelada_en' => $estado === 'cancelada' ? $fecha->copy()->addHours(random_int(1, 10)) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
                $totalCreated++;

                if (count($rows) >= 3000) {
                    Appointment::insert($rows);
                    $rows = [];
                    $this->command->info("  ...{$totalCreated} citas insertadas");
                }
            }
        }

        if (! empty($rows)) {
            Appointment::insert($rows);
        }

        $this->command->info("Citas sembradas: {$totalCreated}");
    }

    private function randomBusinessDate(Carbon $start, Carbon $end): Carbon
    {
        do {
            $days = random_int(0, (int) $start->diffInDays($end));
            $date = $start->copy()->addDays($days);
        } while ($date->dayOfWeek === Carbon::SUNDAY);

        return $date;
    }

    /** @return array{0:string,1:string} */
    private function randomSlot(int $duracionMin): array
    {
        $hour = random_int(9, 19);
        $minute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
        $inicio = Carbon::createFromTime($hour, $minute, 0);
        $fin = $inicio->copy()->addMinutes(max($duracionMin, 15));

        return [$inicio->format('H:i:s'), $fin->format('H:i:s')];
    }

    private function weightedState(): string
    {
        $roll = random_int(1, 100);
        $cumulative = 0;
        foreach (self::PAST_STATES as $state => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $state;
            }
        }

        return 'completada';
    }

    private function uniqueCode(array &$used): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (isset($used[$code]));
        $used[$code] = true;

        return $code;
    }
}
