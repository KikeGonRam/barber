<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsInsightService;
use Illuminate\View\View;

/**
 * Página dedicada de "Analítica" (separada del dashboard principal, a
 * diferencia de las tarjetas sueltas que ya viven ahí) — un solo lugar
 * donde cada rol ve, con pestañas y gráficas, TODO lo que Spark calculó
 * para él. Reutiliza el mismo AnalyticsInsightService del dashboard: aquí
 * no se agrega ninguna regla nueva de "quién ve qué", solo una vista más
 * completa de los mismos datos ya filtrados por rol.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsInsightService $insights) {}

    public function index(): View
    {
        $user = request()->user();

        // Mismo patrón de branching por rol que DashboardController — cada
        // rol solo recibe SU propio recorte de insights (ver el servicio).
        if ($user->hasRoleName('administrador')) {
            $insights = $this->insights->forAdmin();
            $rolLabel = 'administrador';
        } elseif ($user->hasRoleName('recepcionista')) {
            $insights = $this->insights->forReception();
            $rolLabel = 'recepcionista';
        } elseif ($user->hasRoleName('barbero') && $user->barberProfile) {
            $insights = $this->insights->forBarber((string) $user->id, (string) $user->barberProfile->id);
            $rolLabel = 'barbero';
        } elseif ($user->hasRoleName('cliente')) {
            $insights = $this->insights->forClient();
            $rolLabel = 'cliente';
        } else {
            $insights = collect();
            $rolLabel = 'invitado';
        }

        // Agrupar por unidad (I-V) para las pestañas de la vista — el orden
        // importa: es el mismo orden didáctico en que se explican en el
        // manual técnico del proyecto Spark.
        $porUnidad = $insights->groupBy('unidad');

        return view('analytics.index', [
            'rolLabel' => $rolLabel,
            'insights' => $insights,
            'porUnidad' => $porUnidad,
            // Cuántos de los insights de este rol traen gráfica — si es 0,
            // la vista puede saltarse por completo el bloque de Chart.js.
            'tieneGraficas' => $insights->contains(fn ($i) => ! empty($i->grafica)),
        ]);
    }
}
