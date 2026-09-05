<?php

namespace App\Services\Loyalty;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\RaffleResult;

/**
 * Punto único para consultar y reclamar el premio de la rifa mensual de un
 * cliente. Antes de esto, un RaffleResult se creaba y notificaba pero nunca
 * se podía verificar ni consumir en ningún lado — nada impedía que se
 * reclamara más de una vez, ni existía una fecha límite.
 */
class RaffleService
{
    // Premio más reciente del cliente que todavía se puede reclamar (ni
    // reclamado, ni caducado). null si no tiene ninguno vigente.
    public function activePrizeFor(Client $client): ?RaffleResult
    {
        // Carbon object, no string: comparar 'vence_en' (cast 'datetime')
        // contra un string no hace match en MongoDB (ver el mismo caso ya
        // documentado en BarberDashboardController/BarberPerformanceService).
        return RaffleResult::where('client_id', (string) $client->id)
            ->whereNull('reclamado_en')
            ->where('vence_en', '>=', now())
            ->latest('created_at')
            ->first();
    }

    // Marca el premio como reclamado en la cita indicada. Efecto secundario:
    // persiste reclamado_en/appointment_id; no revierte nada si ya estaba
    // reclamado — el llamador debe verificar isRedeemable() antes de cobrar.
    public function claim(RaffleResult $prize, Appointment $appointment): void
    {
        $prize->update([
            'reclamado_en' => now(),
            'appointment_id' => (string) $appointment->id,
        ]);
    }
}
