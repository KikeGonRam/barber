<?php

namespace App\Http\Controllers\Api\Raffle;

use App\Http\Controllers\Controller;
use App\Models\RaffleResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de historial de sorteos mensuales (Fase 9.9), puerto de
 * Loyalty\RaffleController (web): quién ganó, qué premio, y si ya lo
 * reclamó/sigue vigente/caducó. Solo administradores.
 */
class RaffleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $results = RaffleResult::query()
            ->with('client.user:id,name')
            ->orderByDesc('mes')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $results->getCollection()->map(fn (RaffleResult $r) => [
                'id' => $r->id,
                'mes' => $r->mes,
                'premio' => $r->premio,
                'nivel_ganador' => $r->nivel_ganador,
                'vence_en' => optional($r->vence_en)->toDateString(),
                'reclamado_en' => optional($r->reclamado_en)?->toAtomString(),
                'client' => ['id' => $r->client?->id, 'user' => ['name' => $r->client?->user?->name]],
                'is_claimed' => $r->isClaimed(),
                'is_expired' => $r->isExpired(),
            ])->values(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'stats' => [
                'total' => RaffleResult::count(),
                'reclamados' => RaffleResult::whereNotNull('reclamado_en')->count(),
                'vigentes' => RaffleResult::whereNull('reclamado_en')->where('vence_en', '>=', now())->count(),
            ],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
