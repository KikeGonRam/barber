<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

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
                'data' => $this->dashboardService->barberMetrics($user->barberProfile->id),
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
