<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Siembra ÚNICAMENTE la colección `payments` — un pago en efectivo por cada
 * cita completada que aún no tenga uno registrado.
 *
 * Es idempotente: puede correrse varias veces sin duplicar pagos, porque
 * solo procesa citas completadas sin un payment existente.
 *
 * Ejecutar DESPUÉS de AppointmentSeeder.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = (string) User::where('email', 'al222310427@gmail.com')->value('_id');

        $existingPaymentApptIds = Payment::pluck('appointment_id')
            ->map(fn ($id) => (string) $id)
            ->flip();

        $completed = Appointment::where('estado', 'completada')
            ->get(['_id', 'precio_cobrado', 'created_at']);

        if ($completed->isEmpty()) {
            $this->command->error('PaymentSeeder: no hay citas completadas. Ejecuta AppointmentSeeder primero.');
            return;
        }

        $pendientes = $completed->reject(fn ($apt) => $existingPaymentApptIds->has((string) $apt->_id));

        if ($pendientes->isEmpty()) {
            $this->command->info('  ✓ Todas las citas completadas ya tienen pago registrado.');
            return;
        }

        $total = 0;
        foreach ($pendientes->chunk(1000) as $batch) {
            DB::reconnect();
            $docs = $batch->map(function ($apt) use ($adminId) {
                // Insertar el Carbon crudo de $apt->created_at (en vez de convertirlo
                // a UTCDateTime) hace que el driver de Mongo lo serialice como un
                // sub-documento con sus propiedades internas, no como una fecha real.
                // Eso rompe cualquier ->created_at?->format(...) en las vistas con
                // "preg_match(): Argument #2 ($subject) must be of type string, array given".
                $ts = new UTCDateTime($apt->created_at->getTimestamp() * 1000);

                return [
                    '_id'             => new ObjectId(),
                    'appointment_id'  => (string) $apt->_id,
                    'monto'           => (float) $apt->precio_cobrado,
                    'metodo_pago'     => 'efectivo',
                    'propina'         => 0.0,
                    'comprobante_pdf' => null,
                    'created_by'      => $adminId,
                    'created_at'      => $ts,
                    'updated_at'      => $ts,
                ];
            })->values()->all();

            Payment::raw(fn ($col) => $col->insertMany($docs));
            $total += count($docs);
            $this->command->info("  {$total} pagos creados...");
        }

        $this->command->info("  ✓ {$total} pagos creados");
    }
}
