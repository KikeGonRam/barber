<?php

namespace App\Http\Controllers\Api\Membership;

use App\Exceptions\Domain\MembershipException;
use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Services\Membership\MembershipService;
use App\Services\Payment\StripePaymentService;
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
        private readonly StripePaymentService $stripe,
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

    /**
     * Cobros mensuales de membresía del cliente autenticado, para "Mis facturas" (antes solo
     * contaban para el corte de caja y el cliente no los veía).
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $membershipIds = ClientMembership::where('client_id', (string) $user->clientProfile->getKey())
            ->get()
            ->map(fn (ClientMembership $membership) => (string) $membership->getKey())
            ->all();

        $invoices = MembershipInvoice::whereIn('client_membership_id', $membershipIds)
            ->with('membership.plan')
            ->orderByDesc('pagado_en')
            ->get();

        return response()->json([
            'data' => $invoices->map(fn (MembershipInvoice $invoice) => [
                'id' => (string) $invoice->getKey(),
                'plan' => $this->planName($invoice),
                'monto' => (float) $invoice->getAttribute('monto'),
                'pagado_en' => optional($invoice->getAttribute('pagado_en'))->toIso8601String(),
            ])->values(),
            'meta' => [
                'total_pagado' => (float) $invoices->sum(fn (MembershipInvoice $invoice) => (float) $invoice->getAttribute('monto')),
            ],
        ]);
    }

    /**
     * Liga al PDF de la factura de Stripe de un cobro de membresía, solo para su dueño.
     */
    public function invoiceReceiptLink(Request $request, MembershipInvoice $invoice): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $membership = $invoice->membership;
        abort_unless(
            $membership instanceof ClientMembership
                && (string) $membership->getAttribute('client_id') === (string) $user->clientProfile->getKey(),
            403,
            'No autorizado.'
        );

        try {
            $url = $this->stripe->invoicePdfUrl((string) $invoice->getAttribute('stripe_invoice_id'));
        } catch (Throwable $exception) {
            Log::warning('No se pudo obtener el PDF de la factura de membresía.', ['invoice_id' => (string) $invoice->getKey(), 'error' => $exception->getMessage()]);
            $url = null;
        }

        if ($url === null) {
            return response()->json(['message' => 'La factura todavía no está disponible. Intenta más tarde.'], 422);
        }

        return response()->json([
            'data' => [
                'invoice_id' => (string) $invoice->getKey(),
                'receipt_url' => $url,
            ],
        ]);
    }

    private function planName(MembershipInvoice $invoice): ?string
    {
        $membership = $invoice->getRelation('membership');
        $plan = $membership instanceof ClientMembership ? $membership->getRelation('plan') : null;

        return $plan instanceof MembershipPlan ? $plan->getAttribute('nombre') : null;
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
