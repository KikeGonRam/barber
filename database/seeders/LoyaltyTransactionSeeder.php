<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Database\Seeder;

class LoyaltyTransactionSeeder extends Seeder
{
    private const PUNTOS_POR_CITA = 10;

    public function run(): void
    {
        $rows = [];
        $totalTx = 0;
        /** @var array<string,int> $citasPorCliente */
        $citasPorCliente = [];

        Appointment::query()
            ->where('estado', 'completada')
            ->select(['_id', 'client_id', 'created_at'])
            ->chunkById(2000, function ($appointments) use (&$rows, &$totalTx, &$citasPorCliente) {
                foreach ($appointments as $appt) {
                    $clientId = (string) $appt->client_id;
                    $citasPorCliente[$clientId] = ($citasPorCliente[$clientId] ?? 0) + 1;
                    $timestamp = $appt->created_at ?? now();

                    $rows[] = [
                        'client_id' => $clientId,
                        'tipo' => 'ganado',
                        'puntos' => self::PUNTOS_POR_CITA,
                        'descripcion' => 'Cita completada',
                        'referencia_id' => (string) $appt->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                    $totalTx++;

                    if (count($rows) >= 3000) {
                        \App\Models\LoyaltyTransaction::insert($rows);
                        $rows = [];
                    }
                }
            });

        if (! empty($rows)) {
            \App\Models\LoyaltyTransaction::insert($rows);
        }

        $updated = 0;
        foreach ($citasPorCliente as $clientId => $totalCitas) {
            $nivel = LoyaltyService::nivelFromCitas($totalCitas);
            Client::where('_id', $clientId)->update([
                'total_citas' => $totalCitas,
                'puntos' => $totalCitas * self::PUNTOS_POR_CITA,
                'nivel' => $nivel,
            ]);
            $updated++;
        }

        $this->command->info("Transacciones de lealtad sembradas: {$totalTx} · clientes actualizados: {$updated}");
    }
}
