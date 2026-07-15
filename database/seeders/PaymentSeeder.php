<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $staffId = (string) (User::whereRoleName('recepcionista')->first()?->id
            ?? User::whereRoleName('administrador')->first()?->id);

        $rows = [];
        $total = 0;

        Appointment::query()
            ->where('estado', 'completada')
            ->select(['_id', 'precio_cobrado', 'metodo_pago', 'created_at'])
            ->chunkById(2000, function ($appointments) use (&$rows, &$total, $staffId) {
                foreach ($appointments as $appt) {
                    $monto = (float) ($appt->precio_cobrado ?? 0);
                    if ($monto <= 0) {
                        continue;
                    }
                    $propina = round($monto * (random_int(0, 20) / 100), 2);
                    $timestamp = $appt->created_at ?? now();

                    $rows[] = [
                        'appointment_id' => (string) $appt->id,
                        'monto' => $monto,
                        'metodo_pago' => $appt->metodo_pago ?? 'efectivo',
                        'propina' => $propina,
                        'created_by' => $staffId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                    $total++;

                    if (count($rows) >= 3000) {
                        Payment::insert($rows);
                        $rows = [];
                    }
                }
            });

        if (! empty($rows)) {
            Payment::insert($rows);
        }

        $this->command->info("Pagos sembrados: {$total}");
    }
}
