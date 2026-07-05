<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\LoyaltyTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Siembra ÚNICAMENTE la colección `loyalty_transactions` — 10 puntos por cada
 * cita completada que aún no tenga una transacción registrada.
 *
 * Como los puntos/nivel del cliente (`clients.puntos`, `clients.total_citas`,
 * `clients.nivel`) se derivan directamente de sus citas completadas, este
 * seeder también actualiza esos campos al final — es la misma agregación,
 * no tiene sentido calcularla dos veces en un seeder aparte.
 *
 * Es idempotente: solo procesa citas completadas sin loyalty_transaction
 * existente, y siempre recalcula los totales del cliente desde cero.
 *
 * Ejecutar DESPUÉS de AppointmentSeeder.
 */
class LoyaltyTransactionSeeder extends Seeder
{
    private const PUNTOS_POR_CITA = 10;

    public function run(): void
    {
        $existingLoyaltyRefs = LoyaltyTransaction::pluck('referencia_id')
            ->map(fn ($id) => (string) $id)
            ->flip();

        $completed = Appointment::where('estado', 'completada')
            ->get(['_id', 'client_id', 'created_at']);

        if ($completed->isEmpty()) {
            $this->command->error('LoyaltyTransactionSeeder: no hay citas completadas. Ejecuta AppointmentSeeder primero.');
            return;
        }

        $pendientes = $completed->reject(fn ($apt) => $existingLoyaltyRefs->has((string) $apt->_id));

        $total = 0;
        foreach ($pendientes->chunk(1000) as $batch) {
            DB::reconnect();
            $docs = $batch->map(function ($apt) {
                // Insertar el Carbon crudo de $apt->created_at (en vez de un
                // UTCDateTime) hace que el driver de Mongo lo serialice como un
                // sub-documento con sus propiedades internas, no como una fecha
                // real — rompe cualquier ->created_at?->format(...) en las vistas.
                $ts = new UTCDateTime($apt->created_at->getTimestamp() * 1000);

                return [
                    '_id'           => new ObjectId(),
                    'client_id'     => $apt->client_id,
                    'tipo'          => 'ganado',
                    'puntos'        => self::PUNTOS_POR_CITA,
                    'descripcion'   => 'Cita completada',
                    'referencia_id' => (string) $apt->_id,
                    'created_at'    => $ts,
                    'updated_at'    => $ts,
                ];
            })->values()->all();

            if ($docs) {
                LoyaltyTransaction::raw(fn ($col) => $col->insertMany($docs));
                $total += count($docs);
            }
        }
        $this->command->info("  ✓ {$total} transacciones de lealtad creadas");

        $this->recalcularEstadisticasClientes($completed);
    }

    /** Recalcula puntos/total_citas/nivel de cada cliente desde sus citas completadas. */
    private function recalcularEstadisticasClientes($completed): void
    {
        $porCliente = $completed->groupBy('client_id')->map->count();

        foreach ($porCliente->chunk(200) as $chunk) {
            DB::reconnect();
            foreach ($chunk as $clientId => $citas) {
                Client::where('_id', $clientId)->update([
                    'puntos'      => $citas * self::PUNTOS_POR_CITA,
                    'total_citas' => $citas,
                    'nivel'       => $this->calcNivel($citas),
                ]);
            }
        }

        $this->command->info("  ✓ Estadísticas actualizadas para {$porCliente->count()} clientes");
    }

    private function calcNivel(int $citas): string
    {
        if ($citas >= 20) return 'leyenda';
        if ($citas >= 10) return 'vip';
        if ($citas >= 5)  return 'regular';
        return 'nuevo';
    }
}
