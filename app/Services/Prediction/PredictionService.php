<?php

namespace App\Services\Prediction;

use App\Models\Appointment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genera predicciones de negocio (ingresos, citas, servicios populares,
 * horas pico) combinando agregaciones historicas de MongoDB con un LLM
 * local (Ollama) que interpreta esos datos en lenguaje natural. Tambien
 * calcula insights deterministas (sin LLM) a partir de los mismos datos.
 */
class PredictionService
{
    private string $ollamaUrl = 'http://ollama:11434';

    private string $model = 'qwen2.5:0.5b';

    private array $historicalData = [];

    public function __construct()
    {
        $this->loadHistoricalData();
    }

    /**
     * Carga (o recupera de cache) los datos historicos base usados por
     * todas las predicciones: tendencia de citas, ingresos, distribucion
     * de servicios y de horarios.
     */
    private function loadHistoricalData(): void
    {
        // Cache for 6 hours — prediction data doesn't need to be real-time
        $this->historicalData = Cache::remember('prediction_historical_data', 360, function () {
            $startDate = Carbon::now()->subMonths(12);

            return [
                'daily_appointments' => $this->getAppointmentTrend($startDate),
                'daily_income' => $this->getIncomeTrend($startDate),
                'service_distribution' => $this->getServiceDistribution(),
                'hourly_distribution' => $this->getHourlyDistribution(),
            ];
        });
    }

    private function getAppointmentTrend(Carbon $from): array
    {
        return Appointment::where('estado', 'completada')
            ->where('fecha', '>=', $from)
            ->get(['fecha'])
            ->groupBy(fn ($a) => Carbon::parse($a->fecha)->format('Y-m-d'))
            ->map->count()
            ->sortKeys()
            ->toArray();
    }

    private function getIncomeTrend(Carbon $from): array
    {
        return Payment::where('created_at', '>=', $from)
            ->get(['created_at', 'monto', 'propina'])
            ->groupBy(fn ($p) => Carbon::parse($p->created_at)->format('Y-m-d'))
            ->map(fn ($group) => (float) $group->sum(fn ($p) => (float) $p->monto + (float) $p->propina))
            ->sortKeys()
            ->toArray();
    }

    private function getServiceDistribution(): array
    {
        return Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [Carbon::now()->subMonths(3), Carbon::now()])
            ->with('service:id,nombre')
            ->get(['service_id'])
            ->groupBy('service_id')
            ->map(fn ($group) => [
                'service' => $group->first()->service?->nombre ?? 'Sin servicio',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function getHourlyDistribution(): array
    {
        return Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [Carbon::now()->subMonths(1), Carbon::now()])
            ->get(['hora_inicio'])
            ->groupBy(fn ($a) => (int) substr((string) $a->hora_inicio, 0, 2))
            ->map->count()
            ->sortKeys()
            ->toArray();
    }

    /**
     * Predice ingresos para los proximos N dias. Construye un prompt con
     * datos historicos y llama al LLM local (Ollama) para obtener una
     * estimacion en texto, que luego se parsea a numero.
     */
    public function predictIncome(int $daysAhead = 7): array
    {
        $prompt = $this->buildIncomePrompt($daysAhead);
        $prediction = $this->callOllama($prompt);

        return [
            'period' => "Próximos $daysAhead días",
            'predicted_income' => $this->parseNumericPrediction($prediction, 'income|ingreso|pesos|\\$'),
            'trend' => $this->extractTrend($prediction),
            'confidence' => $this->calculateConfidence($prediction),
            'reasoning' => $prediction,
        ];
    }

    /**
     * Predice el numero de citas esperadas para los proximos N dias y sus
     * horas pico, usando el LLM local sobre el historico de citas.
     */
    public function predictAppointments(int $daysAhead = 7): array
    {
        $prompt = $this->buildAppointmentsPrompt($daysAhead);
        $prediction = $this->callOllama($prompt);

        return [
            'period' => "Próximos $daysAhead días",
            'predicted_appointments' => $this->parseNumericPrediction($prediction, 'citas?|appointm|\\d+\\s*servici'),
            'confidence' => $this->calculateConfidence($prediction),
            'peak_hours' => $this->extractPeakHours($prediction),
            'reasoning' => $prediction,
        ];
    }

    /**
     * Combina el top de servicios historico (calculo directo) con
     * recomendaciones de crecimiento/declive generadas por el LLM.
     */
    public function predictPopularServices(): array
    {
        $prompt = $this->buildServicesPrompt();
        $prediction = $this->callOllama($prompt);

        $services = $this->historicalData['service_distribution'];
        $topServices = array_slice($services, 0, 5);

        return [
            'top_services' => $topServices,
            'growth_services' => $this->identifyGrowingServices(),
            'declining_services' => $this->identifyDecliningServices(),
            'recommendations' => $this->parseRecommendations($prediction),
            'reasoning' => $prediction,
        ];
    }

    /**
     * Identifica horas pico y horas de baja demanda usando el LLM sobre
     * la distribucion historica de citas por hora.
     */
    public function predictPeakHours(): array
    {
        $prompt = $this->buildPeakHoursPrompt();
        $prediction = $this->callOllama($prompt);

        return [
            'peak_hours' => $this->extractPeakHours($prediction),
            'quiet_hours' => $this->extractQuietHours($prediction),
            'recommendations' => $this->parseRecommendations($prediction),
            'reasoning' => $prediction,
        ];
    }

    /**
     * Calcula insights deterministas (sin LLM) sobre ingresos, citas y
     * concentracion de servicios, comparando promedios recientes contra
     * el historico para detectar tendencias al alza/baja.
     */
    public function generateInsights(): array
    {
        $insights = [];

        $incomeData = array_values($this->historicalData['daily_income']);
        if (! empty($incomeData)) {
            $avgIncome = array_sum($incomeData) / count($incomeData);
            $trend = end($incomeData) > $avgIncome ? 'up' : 'down';
            $insights['revenue'] = [
                'status' => $trend === 'up' ? 'positive' : 'warning',
                'message' => 'Los ingresos están en '.($trend === 'up' ? 'tendencia alcista' : 'tendencia bajista'),
                'avg_daily' => round($avgIncome, 2),
            ];
        }

        $appointmentData = array_values($this->historicalData['daily_appointments']);
        if (! empty($appointmentData)) {
            $avgAppointments = array_sum($appointmentData) / count($appointmentData);
            $lastWeekAvg = array_sum(array_slice($appointmentData, -7)) / 7;
            $change = (($lastWeekAvg - $avgAppointments) / $avgAppointments) * 100;

            $status = $change > 5 ? 'positive' : ($change < -5 ? 'warning' : 'neutral');
            $insights['appointments'] = [
                'status' => $status,
                'message' => 'Cambio en citas: '.round($change, 1).'%',
                'avg_daily' => round($avgAppointments, 1),
                'trend_7d' => round($lastWeekAvg, 1),
            ];
        }

        $services = $this->historicalData['service_distribution'];
        if (! empty($services)) {
            $topService = $services[0];
            $totalCount = array_sum(array_column($services, 'count'));
            $concentration = ($topService['count'] / $totalCount) * 100;

            $insights['service_concentration'] = [
                'status' => $concentration > 40 ? 'warning' : 'positive',
                'message' => "Servicio top ({$topService['service']}) representa ".round($concentration, 1).'% de citas',
                'top_service' => $topService['service'],
                'percentage' => round($concentration, 1),
            ];
        }

        return $insights;
    }

    private function buildIncomePrompt(int $daysAhead): string
    {
        $recentIncome = array_slice($this->historicalData['daily_income'], -30);
        $avgIncome = ! empty($recentIncome) ? array_sum($recentIncome) / count($recentIncome) : 0;

        return "Analiza los datos históricos de ingresos diarios de una peluquería:

Promedio diario último mes: \$$avgIncome
Ingresos últimos 7 días: ".implode(', $', array_slice($recentIncome, -7))."

Basándote en esta tendencia, predice los ingresos para los próximos $daysAhead días.
Responde solo con un número aproximado en dólares, sin explicación adicional.";
    }

    private function buildAppointmentsPrompt(int $daysAhead): string
    {
        $recentAppointments = array_slice($this->historicalData['daily_appointments'], -30);
        $avgAppointments = ! empty($recentAppointments) ? array_sum($recentAppointments) / count($recentAppointments) : 0;

        return 'Analiza los datos históricos de citas de una peluquería:

Promedio diario último mes: '.round($avgAppointments, 1).' citas
Citas últimos 7 días: '.implode(', ', array_slice($recentAppointments, -7))."

Basándote en esta tendencia, predice las citas para los próximos $daysAhead días.
Identifica también las horas pico esperadas.
Responde brevemente.";
    }

    private function buildServicesPrompt(): string
    {
        $services = $this->historicalData['service_distribution'];
        $serviceList = implode(', ', array_column($services, 'service'));

        return "Analiza la demanda de servicios en una peluquería:

Servicios más populares: $serviceList

¿Cuáles son los servicios con mayor potencial de crecimiento?
¿Hay servicios que podrían ser descontinuados?
Responde brevemente con recomendaciones.";
    }

    private function buildPeakHoursPrompt(): string
    {
        $hours = $this->historicalData['hourly_distribution'];
        $hoursList = '';
        foreach ($hours as $hour => $count) {
            $hoursList .= "$hour:00 - $count citas, ";
        }

        return "Analiza la distribución de citas por hora en una peluquería:

$hoursList

¿Cuáles son las horas pico? ¿Cuándo hay menos demanda?
Responde brevemente para optimizar el horario.";
    }

    /**
     * Llama a la API de Ollama (LLM local) para generar texto a partir de
     * un prompt. Si Ollama no responde o falla, devuelve un mensaje por
     * defecto en vez de propagar la excepcion (la prediccion es opcional,
     * no debe romper el flujo).
     */
    private function callOllama(string $prompt): string
    {
        try {
            $response = Http::timeout(30)->post(
                "{$this->ollamaUrl}/api/generate",
                [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'temperature' => 0.7,
                ]
            );

            if ($response->successful()) {
                $data = $response->json();

                return $data['response'] ?? 'No se pudo generar la predicción.';
            }
        } catch (\Exception $e) {
            Log::warning('Ollama prediction error: '.$e->getMessage());
        }

        return 'Predicción no disponible en este momento.';
    }

    private function parseNumericPrediction(string $text, string $pattern): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:'.$pattern.')?/i', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    private function extractTrend(string $text): string
    {
        if (preg_match('/(alcista|crecimiento|aumento|up|increase)/i', $text)) {
            return 'up';
        } elseif (preg_match('/(bajista|declive|disminución|down|decrease)/i', $text)) {
            return 'down';
        }

        return 'stable';
    }

    private function extractPeakHours(string $text): array
    {
        $peaks = [];
        if (preg_match_all('/(\d{1,2}):?(\d{0,2})?(?:\s*-\s*\d{1,2})?/i', $text, $matches)) {
            foreach ($matches[1] as $hour) {
                if ($hour >= 8 && $hour <= 22) {
                    $peaks[] = (int) $hour;
                }
            }
        }

        return array_unique($peaks);
    }

    private function extractQuietHours(string $text): array
    {
        $quiet = [];
        if (preg_match_all('/(?:quiet|poco|baja|demanda.*?(\d{1,2}))/i', $text, $matches)) {
            foreach ($matches[1] ?? [] as $hour) {
                if ($hour >= 8 && $hour <= 22) {
                    $quiet[] = (int) $hour;
                }
            }
        }

        return array_unique($quiet);
    }

    private function parseRecommendations(string $text): array
    {
        $recommendations = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line) && strlen($line) > 10) {
                $recommendations[] = $line;
            }
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * Heuristica simple de "confianza" de la respuesta del LLM: parte de
     * una base de 60 y suma puntos si el texto incluye numeros o razones
     * explicitas, tope en 100. No es una metrica estadistica real.
     */
    private function calculateConfidence(string $text): int
    {
        if (empty($text) || strpos($text, 'no disponible') !== false) {
            return 0;
        }

        $length = strlen($text);
        $hasNumbers = preg_match('/\d+/', $text) ? 20 : 0;
        $hasReasons = (preg_match_all('/porque|debido|razón|trend|tendency/i', $text) * 5);

        return min(100, 60 + $hasNumbers + $hasReasons);
    }

    private function identifyGrowingServices(): array
    {
        $last30 = array_slice($this->historicalData['daily_appointments'], -30);
        $last60 = array_slice($this->historicalData['daily_appointments'], -60, 30);

        $recent = ! empty($last30) ? array_sum($last30) / count($last30) : 0;
        $older = ! empty($last60) ? array_sum($last60) / count($last60) : 0;

        if ($recent > $older * 1.1) {
            return array_slice(array_column($this->historicalData['service_distribution'], 'service'), 0, 3);
        }

        return [];
    }

    private function identifyDecliningServices(): array
    {
        $last30 = array_slice($this->historicalData['daily_appointments'], -30);
        $last60 = array_slice($this->historicalData['daily_appointments'], -60, 30);

        $recent = ! empty($last30) ? array_sum($last30) / count($last30) : 0;
        $older = ! empty($last60) ? array_sum($last60) / count($last60) : 0;

        if ($recent < $older * 0.9) {
            return array_slice(array_column($this->historicalData['service_distribution'], 'service'), -3);
        }

        return [];
    }
}
