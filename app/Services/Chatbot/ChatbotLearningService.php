<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

/**
 * Motor de "aprendizaje" ligero del chatbot: guarda preguntas nuevas,
 * feedback de usuarios y detecta intenciones/sinónimos por coincidencia de
 * texto (sin ML real). Todo el estado vive en Cache (no hay tabla dedicada),
 * por lo que el "aprendizaje" es efímero y por usuario/guest.
 */
class ChatbotLearningService
{
    /**
     * Base de datos de sinónimos y variaciones de preguntas
     */
    private array $synonyms = [
        'precio' => ['costo', 'cuánto cuesta', 'valor', 'tarifa', 'cuánto es', 'cuánto cobran'],
        'horario' => ['a qué hora', 'cuándo atienden', 'horarios disponibles', 'qué días', 'cuándo abierto'],
        'cita' => ['reserva', 'agendar', 'agenda', 'quiero ir', 'necesito cita', 'hacer cita'],
        'barbero' => ['maestro', 'estilista', 'peluquero', 'quién atiende', 'profesional'],
        'servicio' => ['qué ofrecen', 'qué hacen', 'servicios disponibles', 'opciones'],
        'ubicación' => ['dónde están', 'dirección', 'cómo llego', 'lugar', 'localización'],
        'cancelar' => ['anular', 'eliminar cita', 'reembolso', 'devolver dinero'],
        'pago' => ['método de pago', 'cómo pago', 'tarjeta', 'efectivo', 'transferencia', 'qr'],
        'trending' => ['popular', 'moda', 'qué está de trend', 'lo nuevo', 'tendencia'],
        'recomendación' => ['qué me recomiendas', 'qué debería', 'sugerencia', 'qué me aconsejas'],
        'fade' => ['desvanecimiento', 'fade haircut', 'degradado'],
        'undercut' => ['corte lateral', 'lados cortos'],
        'pompadour' => ['pompa', 'peinado alto'],
    ];

    /**
     * Aprende una nueva pregunta y la mapea a una categoría.
     * Efecto secundario: escribe/actualiza en Cache (clave por usuario o global si $userId es null).
     */
    public function learnQuestion(string $question, string $category, float $confidence = 0.8, $userId = null): void
    {
        $key = 'chatbot_learned_questions'.($userId ? "_{$userId}" : '');
        $learned = Cache::get($key, []);

        // Normalizar pregunta
        $normalized = $this->normalizeText($question);

        if (! isset($learned[$category])) {
            $learned[$category] = [];
        }

        // Agregar si no existe
        if (! in_array($normalized, array_column($learned[$category], 'normalized'))) {
            $learned[$category][] = [
                'question' => $question,
                'normalized' => $normalized,
                'confidence' => $confidence,
                'learned_at' => now()->toIso8601String(),
                'frequency' => 1,
            ];
        }

        // Limitar a 50 por categoría: evita que la cache crezca sin control
        // (FIFO simple, descarta la más antigua).
        if (count($learned[$category]) > 50) {
            array_shift($learned[$category]);
        }

        Cache::put($key, $learned, 86400 * 30); // 30 días
    }

    /**
     * Registra feedback de una respuesta (si fue útil o no).
     * Efecto secundario: escribe en Cache, clave por usuario (útil para auditar calidad de respuestas).
     */
    public function recordFeedback(string $question, string $response, bool $wasHelpful, $userId = null, float $confidence = 0.8): void
    {
        $key = "chatbot_feedback_{$userId}";
        $feedback = Cache::get($key, []);

        $feedback[] = [
            'question' => $question,
            'response' => $response,
            'helpful' => $wasHelpful,
            'confidence' => $confidence,
            'timestamp' => now()->toIso8601String(),
        ];

        // Limitar a últimos 100
        if (count($feedback) > 100) {
            array_shift($feedback);
        }

        Cache::put($key, $feedback, 86400 * 30); // 30 días
    }

    /**
     * Detecta la intención real detrás de una pregunta por coincidencia de
     * palabras clave (no usa el resultado de findSynonyms/aprendizaje).
     */
    public function detectRealIntent(string $question): string
    {
        $question = strtolower($question);
        $normalized = $this->normalizeText($question);

        // Intenciones específicas por palabra clave
        $intentMap = [
            'precio' => ['costo', 'cuánto cuesta', 'valor', 'tarifa', 'cuánto es', 'cuánto cobran', 'precio'],
            'horario' => ['horario', 'hora', 'cuándo atienden', 'qué días', 'abierto', 'cierre'],
            'cita' => ['cita', 'reserva', 'agendar', 'quiero ir', 'necesito', 'agende'],
            'barbero' => ['barbero', 'maestro', 'estilista', 'quién atiende', 'profesional', 'peluquero'],
            'servicio' => ['servicio', 'ofrecen', 'hacen', 'opciones', 'qué hacen'],
            'ubicación' => ['ubicación', 'dirección', 'dónde', 'cómo llego', 'localización'],
            'cancelar' => ['cancelar', 'anular', 'eliminar', 'reembolso', 'devolver'],
            'pago' => ['pago', 'tarjeta', 'efectivo', 'transferencia', 'qr', 'cómo pago'],
            'trending' => ['trending', 'popular', 'moda', 'tendencia', 'nuevo'],
            'recomendación' => ['recomienda', 'sugiere', 'aconsejas', 'debería'],
            'fade' => ['fade', 'desvanecimiento', 'degradado', 'haircut'],
        ];

        // Buscar coincidencias
        foreach ($intentMap as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $intent;
                }
            }
        }

        // Intención por defecto
        return 'general';
    }

    /**
     * Busca sinónimos y palabras relacionadas contra el diccionario estático $synonyms.
     */
    public function findSynonyms(string $word): array
    {
        $word = strtolower($this->normalizeText($word));
        $results = [];

        foreach ($this->synonyms as $category => $synonymsList) {
            foreach ($synonymsList as $synonym) {
                if (str_contains($synonym, $word) || str_contains($word, $synonym)) {
                    $results[] = $category;
                }
            }
        }

        return $results;
    }

    /**
     * Sugiere una respuesta basada en preguntas aprendidas similares (lee de Cache).
     * Recorre TODAS las preguntas aprendidas por Levenshtein: O(n) por llamada, sin índice.
     */
    public function suggestResponse(string $question, $userId = null): ?string
    {
        $key = 'chatbot_learned_questions'.($userId ? "_{$userId}" : '');
        $learned = Cache::get($key, []);
        $normalized = $this->normalizeText($question);

        $bestMatch = null;
        $bestScore = 0.7; // Umbral mínimo de similitud para aceptar la coincidencia

        foreach ($learned as $category => $questions) {
            foreach ($questions as $item) {
                $similarity = $this->stringSimilarity($normalized, $item['normalized']);

                if ($similarity > $bestScore) {
                    $bestScore = $similarity;
                    $bestMatch = $item;
                }
            }
        }

        return $bestMatch ? $bestMatch['question'] : null;
    }

    /**
     * Calcula similitud entre dos strings usando Levenshtein (0.0 a 1.0, normalizada por longitud).
     */
    public function stringSimilarity(string $str1, string $str2): float
    {
        $str1 = $this->normalizeText($str1);
        $str2 = $this->normalizeText($str2);

        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $lev = $this->levenshteinDistance($str1, $str2);
        $maxLen = max($len1, $len2);

        return 1.0 - ($lev / $maxLen);
    }

    /**
     * Calcula distancia Levenshtein entre dos strings (programación dinámica, matriz completa).
     */
    public function levenshteinDistance(string $s1, string $s2): int
    {
        $len1 = strlen($s1);
        $len2 = strlen($s2);

        if ($len1 === 0) {
            return $len2;
        }
        if ($len2 === 0) {
            return $len1;
        }

        // Crear matriz
        $matrix = [];

        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }

        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }

        // Llenar matriz
        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($s1[$i - 1] === $s2[$j - 1]) ? 0 : 1;

                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,      // delete
                    $matrix[$i][$j - 1] + 1,      // insert
                    $matrix[$i - 1][$j - 1] + $cost  // replace
                );
            }
        }

        return $matrix[$len1][$len2];
    }

    /**
     * Entrena desde historial: recorre el historial de conversación cacheado
     * y llama a learnQuestion() por cada entrada. Efecto secundario: escribe en Cache
     * (vía learnQuestion) por cada pregunta procesada.
     */
    public function trainFromHistory($userId = null, int $limit = 20): array
    {
        $report = [];

        // Obtener historial
        $historyKey = "chatbot_history_{$userId}";
        $history = Cache::get($historyKey, []);

        // Entrenar
        $count = 0;
        foreach (array_slice($history, -$limit) as $entry) {
            if (isset($entry['message']) && isset($entry['response'])) {
                $intent = $this->detectRealIntent($entry['message']);
                $this->learnQuestion($entry['message'], $intent, 0.8, $userId);
                $count++;
            }
        }

        $report['trained_questions'] = $count;
        $report['total_history'] = count($history);
        $report['timestamp'] = now()->toIso8601String();

        return $report;
    }

    /**
     * Obtiene reporte de aprendizaje: agrega estadísticas de preguntas aprendidas por categoría (lee de Cache).
     */
    public function getLearningReport($userId = null): array
    {
        $key = 'chatbot_learned_questions'.($userId ? "_{$userId}" : '');
        $learned = Cache::get($key, []);

        $totalQuestions = 0;
        $categoriesInfo = [];

        foreach ($learned as $category => $questions) {
            $totalQuestions += count($questions);
            $categoriesInfo[$category] = [
                'count' => count($questions),
                'examples' => array_slice(array_column($questions, 'question'), 0, 3),
            ];
        }

        return [
            'total_categories' => count($learned),
            'total_learned_questions' => $totalQuestions,
            'categories' => $categoriesInfo,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Top categorías: las N categorías con más preguntas aprendidas, ordenadas descendente.
     */
    public function getTopCategories($userId = null, int $limit = 5): array
    {
        $key = 'chatbot_learned_questions'.($userId ? "_{$userId}" : '');
        $learned = Cache::get($key, []);

        // Contar
        $categoriesCounts = [];
        foreach ($learned as $category => $questions) {
            $categoriesCounts[$category] = count($questions);
        }

        // Ordenar
        arsort($categoriesCounts);

        // Retornar top
        return array_slice(array_keys($categoriesCounts), 0, $limit);
    }

    /**
     * Normaliza texto
     */
    private function normalizeText(string $text): string
    {
        $text = strtolower($text);

        // Quitar acentos
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ã' => 'a', 'õ' => 'o',
            'ñ' => 'n',
        ];

        foreach ($replacements as $char => $replacement) {
            $text = str_replace($char, $replacement, $text);
        }

        // Quitar puntuación
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        // Espacios simples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
