<?php

namespace App\Services\Package;

use App\Exceptions\Domain\GiftCardException;
use App\Models\Client;
use App\Models\GiftCard;
use App\Services\Payment\StripePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tarjetas de regalo (roadmap P1): saldo prepagado de monto libre, no ligado
 * a un servicio -- a diferencia de PackageService (N usos de UN servicio),
 * esta se aplica parcialmente a cualquier cobro hasta agotar el saldo.
 */
class GiftCardService
{
    // Límites de monto para evitar tanto tarjetas simbólicas sin sentido
    // como un error de captura con varios ceros de más.
    public const MONTO_MIN = 100.0;

    public const MONTO_MAX = 5000.0;

    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    /**
     * Compra en efectivo: se activa de inmediato, sin cola de revisión
     * (mismo alcance que PackageService::purchaseCash() -- ver el commit de
     * esa feature para el motivo de no soportar transferencia todavía).
     */
    public function purchaseCash(float $monto, ?Client $comprador, ?string $compradorNombre, ?string $destinatarioEmail, string $createdBy): GiftCard
    {
        $this->guardMonto($monto);

        return $this->create($monto, $comprador, $compradorNombre, $destinatarioEmail, 'efectivo', null, $createdBy);
    }

    public function createStripeIntent(float $monto, ?Client $comprador, ?string $compradorNombre, ?string $destinatarioEmail): array
    {
        $this->guardMonto($monto);

        return $this->stripe->createPaymentIntent(
            $monto,
            'mxn',
            [
                'tipo' => 'gift_card',
                'client_id' => $comprador ? (string) $comprador->id : '',
                'comprador_nombre' => $compradorNombre ?? '',
                'destinatario_email' => $destinatarioEmail ?? '',
                'monto' => (string) $monto,
            ]
        );
    }

    public function confirmStripePurchase(float $monto, ?Client $comprador, ?string $compradorNombre, ?string $destinatarioEmail, string $stripePaymentId): void
    {
        if (GiftCard::where('stripe_payment_id', $stripePaymentId)->exists()) {
            Log::info('Stripe webhook: compra de gift card ya registrada, se omite', ['stripe_payment_id' => $stripePaymentId]);

            return;
        }

        $this->create($monto, $comprador, $compradorNombre, $destinatarioEmail, 'tarjeta', $stripePaymentId, null);
    }

    private function guardMonto(float $monto): void
    {
        if ($monto < self::MONTO_MIN || $monto > self::MONTO_MAX) {
            throw new GiftCardException('El monto de la tarjeta de regalo debe estar entre $'.self::MONTO_MIN.' y $'.self::MONTO_MAX.'.');
        }
    }

    private function create(float $monto, ?Client $comprador, ?string $compradorNombre, ?string $destinatarioEmail, string $metodoPago, ?string $stripePaymentId, ?string $createdBy): GiftCard
    {
        return GiftCard::create([
            'monto_inicial' => $monto,
            'saldo' => $monto,
            'comprador_client_id' => $comprador?->id,
            'comprador_nombre' => $compradorNombre,
            'destinatario_email' => $destinatarioEmail,
            'metodo_pago' => $metodoPago,
            'stripe_payment_id' => $stripePaymentId,
            'comprado_en' => now(),
            // Sin vigencia por defecto: sin fecha de política clara del
            // negocio, mejor no caducar dinero real que ya se pagó.
            'expira_en' => null,
            'estado' => GiftCard::ESTADO_ACTIVA,
            'creado_por' => $createdBy,
        ]);
    }

    /**
     * Busca una gift card activa y usable por su código. Null si no existe,
     * no es válida (mismo criterio 404-como-si-no-existiera que
     * AppointmentManageController -- un código de gift card es igual de
     * "adivinable" que un token de gestión de cita).
     */
    public function findRedeemable(string $code): ?GiftCard
    {
        $card = GiftCard::where('code', $code)->first();

        if (! $card || $card->estado !== GiftCard::ESTADO_ACTIVA) {
            return null;
        }

        if ($card->expira_en && $card->expira_en->isPast()) {
            return null;
        }

        return $card;
    }

    /**
     * Aplica hasta $montoDeseado del saldo de la tarjeta (parcial, a
     * diferencia de PackageService::redeem() que es todo-o-nada). Devuelve
     * el monto realmente aplicado. Decrement atómico condicional -- mismo
     * patrón que InventoryService::registerMovement(), aquí con un monto
     * variable en vez de una unidad fija.
     */
    public function apply(GiftCard $giftCard, float $montoDeseado): float
    {
        if ($montoDeseado <= 0) {
            return 0.0;
        }

        $aplicado = round(min((float) $giftCard->saldo, $montoDeseado), 2);

        if ($aplicado <= 0) {
            throw new GiftCardException('Esta tarjeta de regalo ya no tiene saldo.');
        }

        $run = function () use ($giftCard, $aplicado) {
            // saldo es decimal:2 -- comparar/decrementar con el mismo valor
            // redondeado evita que un residuo de punto flotante deje el
            // filtro sin coincidir.
            $affected = GiftCard::query()
                ->where('_id', $giftCard->id)
                ->where('saldo', '>=', $aplicado)
                ->decrement('saldo', $aplicado);

            if ($affected === 0) {
                throw new GiftCardException('Esta tarjeta de regalo ya no tiene saldo suficiente.');
            }

            $fresh = GiftCard::find($giftCard->id);
            if ($fresh && (float) $fresh->saldo <= 0) {
                $fresh->update(['estado' => GiftCard::ESTADO_AGOTADA]);
            }
        };

        // mongodb/laravel-mongodb no soporta transacciones anidadas -- mismo
        // patrón que PackageService::redeem() / InventoryService.
        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);

        return $aplicado;
    }
}
