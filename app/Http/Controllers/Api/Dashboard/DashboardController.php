<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del dashboard unificado.
 * Devuelve métricas distintas según el rol del usuario autenticado
 * (administrador/barbero/recepcionista/cliente), delegando el cálculo a DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    // Enruta al set de métricas correspondiente según el rol del usuario autenticado
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if ($user->hasRole('administrador')) {
            return response()->json([
                'role' => 'administrador',
                'data' => $this->dashboardService->adminMetrics(),
            ]);
        }

        if ($user->hasRole('barbero') && $user->barberProfile) {
            return response()->json([
                'role' => 'barbero',
                'data' => $this->dashboardService->barberMetrics((string) $user->barberProfile->id),
            ]);
        }

        if ($user->hasRole('recepcionista')) {
            return response()->json([
                'role' => 'recepcionista',
                'data' => $this->dashboardService->receptionistMetrics(),
            ]);
        }

        if ($user->hasRole('cliente') && $user->clientProfile) {
            return response()->json([
                'role' => 'cliente',
                'data' => $this->dashboardService->clientMetrics($user->clientProfile->id),
            ]);
        }

        return response()->json([
            'role' => 'guest',
            'data' => [],
        ]);
    }
}
