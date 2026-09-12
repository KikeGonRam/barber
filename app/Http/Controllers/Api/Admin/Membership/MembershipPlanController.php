<?php

namespace App\Http\Controllers\Api\Admin\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * API de administración de planes de membresía recurrente (crear/editar/
 * desactivar/listar), exclusiva para administradores. Cada plan tiene un
 * Product + Price real en Stripe -- ver StripePaymentService::createMonthlyPrice().
 */
class MembershipPlanController extends Controller
{
    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $plans = MembershipPlan::latest()->get();

        return response()->json([
            'data' => $plans->map(fn (MembershipPlan $p) => $this->payload($p))->values(),
        ]);
    }

    /**
     * Crea un plan nuevo: primero el Price recurrente en Stripe, luego el
     * registro local. Si Stripe falla no se crea nada local (evita un plan
     * "fantasma" sin price_id que rompería subscribe() más adelante).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'precio_mensual' => ['required', 'numeric', 'min:1'],
            'descuento_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ]);

        try {
            $stripeData = $this->stripe->createMonthlyPrice($data['nombre'], (float) $data['precio_mensual']);
        } catch (Throwable $exception) {
            Log::warning('No se pudo crear el Price de Stripe para el plan de membresía.', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => 'No se pudo crear el plan en Stripe. Intenta de nuevo.',
            ], 422);
        }

        $plan = MembershipPlan::create($data + [
            'activo' => $data['activo'] ?? true,
            'stripe_product_id' => $stripeData['product_id'],
            'stripe_price_id' => $stripeData['price_id'],
        ]);

        return response()->json([
            'message' => 'Plan de membresía creado correctamente.',
            'data' => $this->payload($plan),
        ], 201);
    }

    /**
     * Actualiza un plan existente. Si cambia precio_mensual se crea un Price
     * nuevo en Stripe (los Price son inmutables) -- las suscripciones ya
     * activas conservan el Price original hasta su próxima renovación, igual
     * que ServicePackageController::update() no toca los ClientPackage ya
     * comprados.
     */
    public function update(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'precio_mensual' => ['required', 'numeric', 'min:1'],
            'descuento_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $precioCambio = round((float) $membershipPlan->precio_mensual, 2) !== round((float) $data['precio_mensual'], 2);

        if ($precioCambio) {
            try {
                $stripeData = $this->stripe->createMonthlyPrice($data['nombre'], (float) $data['precio_mensual']);
                $data['stripe_price_id'] = $stripeData['price_id'];
                $data['stripe_product_id'] = $stripeData['product_id'];
            } catch (Throwable $exception) {
                Log::warning('No se pudo crear el nuevo Price de Stripe al actualizar el plan.', ['error' => $exception->getMessage()]);

                return response()->json([
                    'message' => 'No se pudo actualizar el precio en Stripe. Intenta de nuevo.',
                ], 422);
            }
        }

        $membershipPlan->update($data);

        return response()->json([
            'message' => 'Plan de membresía actualizado correctamente.',
            'data' => $this->payload($membershipPlan->fresh()),
        ]);
    }

    /**
     * Desactiva un plan (deja de venderse) en vez de eliminarlo -- las
     * ClientMembership ya contratadas siguen su ciclo normal.
     */
    public function destroy(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        $this->authorizeAdmin($request);

        $membershipPlan->update(['activo' => false]);

        return response()->json([
            'message' => 'Plan desactivado. Las membresías ya contratadas no se ven afectadas.',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }

    private function payload(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
            'precio_mensual' => $plan->precio_mensual,
            'descuento_pct' => $plan->descuento_pct,
            'activo' => (bool) $plan->activo,
        ];
    }
}
