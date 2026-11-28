<?php

namespace App\Console\Commands;

use App\Models\ClientMembership;
use App\Services\Membership\MembershipService;
use Illuminate\Console\Command;

/**
 * Concilia con Stripe las membresías que quedaron atrasadas (primer pago sin confirmar, cobro
 * fallido o periodo vencido), para no depender de que llegue el webhook ni de que el cliente
 * abra su pantalla de membresía.
 */
class ReconcileMembershipsCommand extends Command
{
    protected $signature = 'memberships:reconcile';

    protected $description = 'Concilia con Stripe las membresías atrasadas';

    public function handle(MembershipService $memberships): int
    {
        $revisadas = 0;

        foreach (ClientMembership::where('bloquea_membresia', true)->get() as $membership) {
            if ($memberships->needsReconcile($membership)) {
                $memberships->reconcileWithStripe($membership);
                $revisadas++;
            }
        }

        $this->info("Membresías conciliadas con Stripe: {$revisadas}");

        return self::SUCCESS;
    }
}
