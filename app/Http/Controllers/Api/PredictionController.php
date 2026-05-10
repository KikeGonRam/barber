<?php

namespace App\Http\Controllers\Api;

use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;

class PredictionController
{
    public function __construct(private PredictionService $predictionService) {}

    public function incomeForecasting(int $days = 7): JsonResponse
    {
        return response()->json([
            'type' => 'income_forecast',
            'data' => $this->predictionService->predictIncome($days),
        ]);
    }

    public function appointmentForecast(int $days = 7): JsonResponse
    {
        return response()->json([
            'type' => 'appointment_forecast',
            'data' => $this->predictionService->predictAppointments($days),
        ]);
    }

    public function serviceAnalysis(): JsonResponse
    {
        return response()->json([
            'type' => 'service_analysis',
            'data' => $this->predictionService->predictPopularServices(),
        ]);
    }

    public function peakHoursAnalysis(): JsonResponse
    {
        return response()->json([
            'type' => 'peak_hours',
            'data' => $this->predictionService->predictPeakHours(),
        ]);
    }

    public function insights(): JsonResponse
    {
        return response()->json([
            'type' => 'insights',
            'data' => $this->predictionService->generateInsights(),
        ]);
    }
}
