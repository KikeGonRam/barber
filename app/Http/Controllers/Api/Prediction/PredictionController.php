<?php

namespace App\Http\Controllers\Api\Prediction;

use App\Services\Prediction\PredictionService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de API de predicciones/analítica (ML) del negocio: ingresos, citas,
 * servicios populares y horas pico. Expone los resultados de PredictionService como JSON.
 */
class PredictionController
{
    public function __construct(private PredictionService $predictionService) {}

    // Defensa en profundidad: aunque la ruta ya exige role.custom:administrador,
    // este guard evita que un descuido en routes/api.php exponga proyecciones de ingresos.
    private function authorizeAdmin(): void
    {
        abort_if(! request()->user()?->hasRole('administrador'), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    /**
     * Proyección de ingresos futuros a N días calculada por el servicio de predicción.
     */
    public function incomeForecasting(int $days = 7): JsonResponse
    {
        $this->authorizeAdmin();
        // Ollama en CPU puede tardar mas que max_execution_time por defecto
        // de PHP-FPM (30s); el cliente HTTP ya espera hasta OLLAMA_TIMEOUT
        // (90s por defecto), asi que el limite de PHP debe ser mayor.
        set_time_limit(120);

        return response()->json([
            'type' => 'income_forecast',
            'data' => $this->predictionService->predictIncome($days),
        ]);
    }

    /**
     * Proyección de volumen de citas futuras a N días.
     */
    public function appointmentForecast(int $days = 7): JsonResponse
    {
        $this->authorizeAdmin();
        // Ollama en CPU puede tardar mas que max_execution_time por defecto
        // de PHP-FPM (30s); el cliente HTTP ya espera hasta OLLAMA_TIMEOUT
        // (90s por defecto), asi que el limite de PHP debe ser mayor.
        set_time_limit(120);

        return response()->json([
            'type' => 'appointment_forecast',
            'data' => $this->predictionService->predictAppointments($days),
        ]);
    }

    /**
     * Análisis de qué servicios tienen mayor demanda, según el histórico.
     */
    public function serviceAnalysis(): JsonResponse
    {
        $this->authorizeAdmin();
        // Ollama en CPU puede tardar mas que max_execution_time por defecto
        // de PHP-FPM (30s); el cliente HTTP ya espera hasta OLLAMA_TIMEOUT
        // (90s por defecto), asi que el limite de PHP debe ser mayor.
        set_time_limit(120);

        return response()->json([
            'type' => 'service_analysis',
            'data' => $this->predictionService->predictPopularServices(),
        ]);
    }

    /**
     * Identifica los horarios de mayor afluencia para apoyar planificación de personal.
     */
    public function peakHoursAnalysis(): JsonResponse
    {
        $this->authorizeAdmin();
        // Ollama en CPU puede tardar mas que max_execution_time por defecto
        // de PHP-FPM (30s); el cliente HTTP ya espera hasta OLLAMA_TIMEOUT
        // (90s por defecto), asi que el limite de PHP debe ser mayor.
        set_time_limit(120);

        return response()->json([
            'type' => 'peak_hours',
            'data' => $this->predictionService->predictPeakHours(),
        ]);
    }

    /**
     * Genera un resumen de insights combinados (ingresos, citas, servicios, horas pico)
     * para mostrar recomendaciones accionables en el dashboard.
     */
    public function insights(): JsonResponse
    {
        $this->authorizeAdmin();
        // Ollama en CPU puede tardar mas que max_execution_time por defecto
        // de PHP-FPM (30s); el cliente HTTP ya espera hasta OLLAMA_TIMEOUT
        // (90s por defecto), asi que el limite de PHP debe ser mayor.
        set_time_limit(120);

        return response()->json([
            'type' => 'insights',
            'data' => $this->predictionService->generateInsights(),
        ]);
    }
}
