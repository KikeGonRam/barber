<?php

namespace App\Http\Controllers\Api\Membership;

use App\Exceptions\Domain\MembershipException;
use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Services\Membership\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Membresías
 *
 * Suscripción mensual recurrente (roadmap P1, vía Stripe Subscriptions) que
 * otorga un descuento % en cada servicio mientras esté activa. La venta y el
 * cobro real de las renovaciones ocurren enteramente en Stripe -- este
 * controlador solo contrata, consulta y cancela.
 */
class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipService $memberships,
    ) {}

    /**
     * Catálogo de planes disponibles para contratar.
     */
    public function plans(): JsonResponse
    {
        $plans = $this->memberships->activePlans();

        return response()->json([
            'data' => $plans->map(fn (MembershipPlan $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'precio_mensual' => $p->precio_mensual,
                'descuento_pct' => $p->descuento_pct,
            ])->values(),
        ]);
    }

    /**
     * Membresía actual del cliente autenticado (o null si no tiene ninguna).
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $membership = $this->memberships->currentFor($user->clientProfile);

        return response()->json([
            'data' => $membership ? $this->payload($membership->fresh('plan')) : null,
        ]);
    }

    /**
     * Contrata un plan. Devuelve el client_secret para que el frontend
     * confirme el primer pago con Stripe Elements (mismo patrón SCA que el
     * resto de los cobros con tarjeta de esta app).
     *
     * @bodyParam membership_plan_id string required ID del plan a contratar. Example: 66f0...
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $validated = $request->validate([
            'membership_plan_id' => ['required', 'string', 'exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        try {
            $result = $this->memberships->subscribe($user->clientProfile, $plan);

            return response()->json([
                'data' => [
                    'client_secret' => $result['client_secret'],
                    'membership' => $this->payload($result['membership']->fresh('plan')),
                ],
            ], 201);
        } catch (MembershipException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::warning('No se pudo crear la suscripción de membresía en Stripe.', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => 'No se pudo procesar la membresía en este momento. Intenta de nuevo más tarde.',
            ], 422);
        }
    }

    /**
     * Cancela la membresía al final del periodo ya pagado.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $membership = $this->memberships->currentFor($user->clientProfile);
        abort_if(! $membership, 404, 'No tienes una membresía.');

        try {
            $membership = $this->memberships->cancel($membership);
        } catch (MembershipException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Tu membresía se cancelará al final del periodo ya pagado.',
            'data' => $this->payload($membership->fresh('plan')),
        ]);
    }

    private function payload(ClientMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'estado' => $membership->estado,
            'cancelar_al_finalizar' => (bool) $membership->cancelar_al_finalizar,
            'periodo_actual_fin' => optional($membership->periodo_actual_fin)->toIso8601String(),
            'plan' => [
                'id' => $membership->plan?->id,
                'nombre' => $membership->plan?->nombre,
                'precio_mensual' => $membership->plan?->precio_mensual,
                'descuento_pct' => $membership->plan?->descuento_pct,
            ],
        ];
    }
}
