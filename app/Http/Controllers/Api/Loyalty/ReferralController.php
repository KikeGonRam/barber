<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Exceptions\Domain\ReferralException;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Referidos
 *
 * Cada cliente tiene un código propio para invitar. Quien invita gana
 * puntos de lealtad cuando la persona referida completa su primera cita
 * (nunca por solo registrarse) -- ver ReferralService.
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
    ) {}

    /**
     * Mi código y mis referidos: a quién he invitado y cuántos ya
     * completaron su primera cita (y me dieron puntos).
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $client = $user->clientProfile;

        $referidos = Referral::where('referrer_client_id', (string) $client->id)
            ->with('referee.user')
            ->latest()
            ->get()
            ->map(fn (Referral $r) => [
                'referido' => $r->referee?->user?->name ?? 'Cliente',
                'estado' => $r->estado,
                'recompensa_otorgada_en' => optional($r->recompensa_otorgada_en)->toIso8601String(),
            ])->values();

        return response()->json([
            'data' => [
                'codigo_referido' => $client->codigo_referido,
                'puntos_por_referido' => LoyaltyService::REFERRAL_POINTS,
                'referidos' => $referidos,
                'completados' => $referidos->where('estado', Referral::ESTADO_COMPLETADO)->count(),
            ],
        ]);
    }

    /**
     * Registra quién me refirió. Solo puede hacerse una vez por cliente.
     *
     * @bodyParam codigo string required Código de referido de quien te invitó. Example: A1B2C3
     */
    public function link(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('cliente') && $user->clientProfile, 403, 'No autorizado.');

        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->referrals->link($user->clientProfile, $validated['codigo']);
        } catch (ReferralException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => '¡Listo! Cuando completes tu primera cita, tu referido ganará sus puntos.',
        ], 201);
    }
}
