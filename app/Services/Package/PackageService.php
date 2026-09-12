<?php

namespace App\Services\Package;

use App\Exceptions\Domain\PackageException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ServicePackage;
use App\Services\Payment\StripePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paquetes prepagados (roadmap P1): un cliente compra N usos de UN servicio
 * por un precio fijo y los va consumiendo cita por cita, sin volver a pagar
 * hasta agotarlos. Vive separado de PaymentService porque la compra no está
 * ligada a ninguna cita (es dinero por adelantado, no el cobro de un
 * servicio ya prestado); PaymentService::create() sí llama a redeem() al
 * cobrar una cita con "usar_paquete_id" en el payload.
 */
class PackageService
{
    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    /**
     * Compra en efectivo: se verifica de inmediato, sin cola de revisión
     * (a diferencia de una transferencia, que este primer alcance no
     * soporta a propósito -- ver el commit de esta feature).
     */
    public function purchaseCash(Client $client, ServicePackage $package, string $createdBy): ClientPackage
    {
        $this->guardPurchasable($package);

        return $this->create($client, $package, 'efectivo', null, $createdBy);
    }

    /**
     * Crea el PaymentIntent de Stripe para comprar el paquete con tarjeta.
     * El monto nunca sale del cliente: se relee de ServicePackage::precio.
     */
    public function createStripeIntent(Client $client, ServicePackage $package): array
    {
        $this->guardPurchasable($package);

        return $this->stripe->createPaymentIntent(
            (float) $package->precio,
            'mxn',
            [
                'tipo' => 'paquete',
                'client_id' => (string) $client->id,
                'service_package_id' => (string) $package->id,
            ]
        );
    }

    /**
     * Registra la compra confirmada por Stripe (llamado desde el webhook).
     * Sin índice único que lo respalde -- comprar el mismo paquete dos veces
     * es válido (dos paquetes independientes, ambos usables) -- así que la
     * idempotencia aquí es solo "no falla si ya existe uno con este mismo
     * stripe_payment_id", igual criterio que el resto de confirmaciones de
     * webhook de este proyecto.
     */
    public function confirmStripePurchase(Client $client, ServicePackage $package, string $stripePaymentId): void
    {
        if (ClientPackage::where('stripe_payment_id', $stripePaymentId)->exists()) {
            Log::info('Stripe webhook: compra de paquete ya registrada, se omite', ['stripe_payment_id' => $stripePaymentId]);

            return;
        }

        $this->create($client, $package, 'tarjeta', $stripePaymentId, null);
    }

    private function guardPurchasable(ServicePackage $package): void
    {
        if (! $package->activo) {
            throw new PackageException('Este paquete ya no está disponible para la venta.');
        }
    }

    private function create(Client $client, ServicePackage $package, string $metodoPago, ?string $stripePaymentId, ?string $createdBy): ClientPackage
    {
        $package->loadMissing('service');

        return ClientPackage::create([
            'client_id' => (string) $client->id,
            'service_package_id' => (string) $package->id,
            'service_id' => (string) $package->service_id,
            'usos_totales' => (int) $package->cantidad_usos,
            'usos_restantes' => (int) $package->cantidad_usos,
            // El precio se congela al momento de la compra: si el paquete
            // sube de precio después, esta instancia ya pagada no cambia.
            'precio_pagado' => (float) $package->precio,
            'metodo_pago' => $metodoPago,
            'stripe_payment_id' => $stripePaymentId,
            'comprado_en' => now(),
            'expira_en' => $package->vigencia_dias ? now()->addDays((int) $package->vigencia_dias) : null,
            'estado' => ClientPackage::ESTADO_ACTIVO,
            'creado_por' => $createdBy,
        ]);
    }

    /**
     * Consume un uso del paquete para cobrar la cita indicada. Exige que el
     * paquete sea del MISMO cliente y MISMO servicio que la cita -- un
     * paquete de "corte clásico" no puede pagar una cita de "barba".
     * Decrement atómico (mismo patrón que InventoryService::registerMovement()):
     * un único $inc con filtro usos_restantes > 0 evita que dos cobros
     * casi simultáneos dejen el contador en negativo.
     */
    public function redeem(ClientPackage $clientPackage, Appointment $appointment): void
    {
        if ((string) $clientPackage->client_id !== (string) $appointment->client_id) {
            throw new PackageException('Este paquete no pertenece al cliente de la cita.');
        }

        if ((string) $clientPackage->service_id !== (string) $appointment->service_id) {
            throw new PackageException('Este paquete es para otro servicio.');
        }

        if ($clientPackage->estado !== ClientPackage::ESTADO_ACTIVO) {
            throw new PackageException('Este paquete ya no está activo.');
        }

        if ($clientPackage->expira_en && $clientPackage->expira_en->isPast()) {
            throw new PackageException('Este paquete ya venció.');
        }

        // mongodb/laravel-mongodb no soporta transacciones anidadas -- ver
        // InventoryService::registerMovement() para el mismo patrón. Aquí
        // siempre se llama desde dentro de la transacción de
        // PaymentService::create(), pero se guarda igual por si algún día
        // se llama standalone.
        $run = fn () => $this->doRedeem($clientPackage);

        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    private function doRedeem(ClientPackage $clientPackage): void
    {
        $affected = ClientPackage::query()
            ->where('_id', $clientPackage->id)
            ->where('usos_restantes', '>', 0)
            ->decrement('usos_restantes', 1);

        if ($affected === 0) {
            throw new PackageException('Este paquete ya no tiene usos disponibles.');
        }

        $fresh = ClientPackage::find($clientPackage->id);
        if ($fresh && (int) $fresh->usos_restantes <= 0) {
            $fresh->update(['estado' => ClientPackage::ESTADO_AGOTADO]);
        }
    }

    /**
     * Paquetes con fecha de vencimiento pasada que aún tenían usos: quedan
     * `expirado` en vez de seguir contando como activos indefinidamente.
     * Llamado por ExpireClientPackagesCommand (diario).
     */
    public function expireStale(): int
    {
        return ClientPackage::where('estado', ClientPackage::ESTADO_ACTIVO)
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', now())
            ->update(['estado' => ClientPackage::ESTADO_EXPIRADO]);
    }
}
