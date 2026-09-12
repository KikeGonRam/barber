<?php

namespace App\Services\Loyalty;

use App\Exceptions\Domain\ReferralException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Referral;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Programa de referidos (roadmap P1): liga un cliente nuevo (referee) con
 * quien lo invitó (referrer) por código, y otorga la recompensa (puntos de
 * lealtad) solo cuando el referido completa su PRIMERA cita real -- nunca
 * por solo registrarse, para no premiar cuentas falsas.
 */
class ReferralService
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * Liga al cliente autenticado con quien lo refirió, por código. Solo
     * puede hacerse UNA VEZ por cliente (índice único sobre referee_client_id)
     * y nunca auto-referirse.
     */
    public function link(Client $referee, string $codigoReferido): Referral
    {
        $referrer = Client::where('codigo_referido', mb_strtoupper(trim($codigoReferido)))->first();

        if (! $referrer) {
            throw new ReferralException('El código de referido no es válido.');
        }

        if ((string) $referrer->id === (string) $referee->id) {
            throw new ReferralException('No puedes usar tu propio código de referido.');
        }

        try {
            return Referral::create([
                'referrer_client_id' => (string) $referrer->id,
                'referee_client_id' => (string) $referee->id,
                'estado' => Referral::ESTADO_PENDIENTE,
            ]);
        } catch (BulkWriteException $e) {
            throw new ReferralException('Ya tienes un código de referido registrado -- no se puede cambiar.');
        }
    }

    /**
     * Se llama cada vez que una cita del cliente se completa por primera
     * vez (mismo punto donde LoyaltyService::awardCitaPoints() ya se
     * dispara, ver PaymentService::completeCharge()). Solo otorga la
     * recompensa si esta es EXACTAMENTE la primera cita completada de su
     * vida como cliente -- llamarlo de nuevo en citas posteriores es un
     * no-op seguro (no hay referral pendiente que encontrar, o el conteo
     * ya no da 1).
     */
    public function completeIfEligible(Client $referee): void
    {
        $referral = Referral::where('referee_client_id', (string) $referee->id)
            ->where('estado', Referral::ESTADO_PENDIENTE)
            ->first();

        if (! $referral) {
            return;
        }

        $completadas = Appointment::where('client_id', (string) $referee->id)
            ->where('estado', 'completada')
            ->count();

        if ($completadas !== 1) {
            return;
        }

        $referrer = Client::find($referral->referrer_client_id);
        if (! $referrer) {
            Log::warning('Referral sin referrer válido al completar primera cita', ['referral_id' => $referral->id]);

            return;
        }

        $this->loyalty->awardReferralPoints($referrer, (string) $referee->id);

        $referral->update([
            'estado' => Referral::ESTADO_COMPLETADO,
            'recompensa_otorgada_en' => now(),
        ]);
    }
}
