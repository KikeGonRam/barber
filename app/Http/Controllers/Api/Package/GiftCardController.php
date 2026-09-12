<?php

namespace App\Http\Controllers\Api\Package;

use App\Exceptions\Domain\GiftCardException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Package\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Tarjetas de regalo
 *
 * Compra (efectivo por staff o tarjeta vía Stripe) y consulta de saldo por
 * código. El canje real ocurre en PaymentController::store() vía
 * "codigo_gift_card" -- este controlador no cobra nada.
 */
class GiftCardController extends Controller
{
    public function __construct(
        private readonly GiftCardService $giftCards,
    ) {}

    /**
     * Consulta el saldo disponible de una gift card por su código. Sin
     * autenticación de dueño a propósito -- una gift card la usa quien
     * tenga el código, igual que una física.
     *
     * @urlParam code string required Código de la tarjeta. Example: a1b2c3d4
     */
    public function show(string $code): JsonResponse
    {
        $giftCard = $this->giftCards->findRedeemable($code);

        if (! $giftCard) {
            return response()->json(['message' => 'Código no válido o tarjeta sin saldo.'], 404);
        }

        return response()->json([
            'data' => [
                'code' => $giftCard->code,
                'saldo' => $giftCard->saldo,
                'monto_inicial' => $giftCard->monto_inicial,
                'estado' => $giftCard->estado,
            ],
        ]);
    }

    /**
     * Compra en efectivo, registrada por staff (Admin/Recepcionista).
     *
     * @bodyParam monto number required Monto de la tarjeta. Example: 500
     * @bodyParam client_id string ID del cliente comprador, si tiene cuenta. Example: 66f0...
     * @bodyParam comprador_nombre string Nombre de quien compra, si no tiene cuenta. Example: Juan Pérez
     * @bodyParam destinatario_email string Correo de quien la recibe (opcional). Example: regalo@ejemplo.com
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');

        $validated = $request->validate([
            'monto' => ['required', 'numeric'],
            'client_id' => ['nullable', 'string', 'exists:clients,id'],
            'comprador_nombre' => ['nullable', 'string', 'max:150'],
            'destinatario_email' => ['nullable', 'email', 'max:255'],
        ]);

        $comprador = ! empty($validated['client_id']) ? Client::find($validated['client_id']) : null;

        try {
            $giftCard = $this->giftCards->purchaseCash(
                (float) $validated['monto'],
                $comprador,
                $validated['comprador_nombre'] ?? null,
                $validated['destinatario_email'] ?? null,
                (string) $user->id,
            );
        } catch (GiftCardException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Tarjeta de regalo vendida correctamente.',
            'data' => ['code' => $giftCard->code, 'saldo' => $giftCard->saldo],
        ], 201);
    }

    /**
     * Crea el PaymentIntent de Stripe para comprar una gift card con tarjeta.
     *
     * @bodyParam monto number required Monto de la tarjeta. Example: 500
     */
    public function stripeIntent(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401);

        $validated = $request->validate([
            'monto' => ['required', 'numeric'],
            'comprador_nombre' => ['nullable', 'string', 'max:150'],
            'destinatario_email' => ['nullable', 'email', 'max:255'],
        ]);

        $comprador = $user->hasRole('cliente') ? $user->clientProfile : null;

        try {
            $data = $this->giftCards->createStripeIntent(
                (float) $validated['monto'],
                $comprador,
                $validated['comprador_nombre'] ?? null,
                $validated['destinatario_email'] ?? null,
            );

            return response()->json(['data' => $data]);
        } catch (GiftCardException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::warning('No se pudo crear intento de pago Stripe para gift card.', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => 'No se pudo crear el intento de pago Stripe. Intenta de nuevo o usa efectivo.',
            ], 422);
        }
    }
}
