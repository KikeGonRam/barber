<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class ChatbotContextService
{
    /**
     * Obtiene el historial de conversación del usuario actual
     */
    public function getConversationHistory($userId = null): array
    {
        $key = $this->getSessionKey($userId);
        return Session::get($key, []);
    }

    /**
     * Guarda un mensaje en el historial
     */
    public function addMessage($message, $response, $type = 'user', $userId = null): void
    {
        $key = $this->getSessionKey($userId);
        $history = Session::get($key, []);

        $history[] = [
            'timestamp' => now()->toIso8601String(),
            'type' => $type, // 'user' o 'bot'
            'message' => $message,
            'response' => $response,
            'context' => $this->extractContext($message),
        ];

        // Limitar a últimos 20 mensajes para no saturar sesión
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        Session::put($key, $history);
    }

    /**
     * Obtiene el último contexto de la conversación
     */
    public function getLastContext($userId = null): ?array
    {
        $history = $this->getConversationHistory($userId);
        
        if (empty($history)) {
            return null;
        }

        $lastItem = end($history);
        return $lastItem['context'] ?? null;
    }

    /**
     * Extrae información clave del mensaje (contexto)
     */
    private function extractContext(string $message): array
    {
        $context = [
            'keywords' => $this->extractKeywords($message),
            'intent' => $this->detectIntent($message),
            'entities' => $this->extractEntities($message),
            'is_followup' => false,
        ];

        return $context;
    }

    /**
     * Extrae palabras clave importantes
     */
    private function extractKeywords(string $message): array
    {
        $keywords = [];
        
        // Palabras importantes para barbería
        $important = [
            'cita', 'servicio', 'barbero', 'precio', 'horario', 'ubicación',
            'fade', 'undercut', 'pompadour', 'corte', 'afeitado',
            'pago', 'cancelación', 'puntos', 'miembro', 'descuento',
            'producto', 'reseña', 'comentario', 'trending', 'popular',
        ];

        foreach ($important as $word) {
            if (str_contains($message, $word)) {
                $keywords[] = $word;
            }
        }

        return $keywords;
    }

    /**
     * Detecta la intención del mensaje
     */
    private function detectIntent(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, '?') || str_contains($message, 'cuál') || str_contains($message, 'qué')) {
            return 'question';
        }
        if (str_contains($message, 'quiero') || str_contains($message, 'agendar') || str_contains($message, 'reservar')) {
            return 'book';
        }
        if (str_contains($message, 'ayuda') || str_contains($message, 'no entiendo') || str_contains($message, 'cómo')) {
            return 'help';
        }
        if (str_contains($message, 'gracias') || str_contains($message, 'ok') || str_contains($message, 'perfecto')) {
            return 'acknowledge';
        }

        return 'general';
    }

    /**
     * Extrae entidades (barberos, servicios, etc.)
     */
    private function extractEntities(string $message): array
    {
        $entities = [
            'services' => [],
            'barbers' => [],
            'times' => [],
        ];

        // Extraer servicios mencionados
        $services = ['fade', 'undercut', 'pompadour', 'afeitado', 'facial', 'masaje', 'corte'];
        foreach ($services as $service) {
            if (str_contains($message, $service)) {
                $entities['services'][] = $service;
            }
        }

        // Extraer referencias a barberos
        if (str_contains($message, 'carlos') || str_contains($message, 'juan') || str_contains($message, 'luis')) {
            $entities['barbers'][] = strtolower(substr($message, strpos($message, 'c')));
        }

        return $entities;
    }

    /**
     * Calcula similitud entre dos preguntas (0-100)
     */
    public function getSimilarity(string $message1, string $message2): float
    {
        $similarity = similar_text($message1, $message2, $percentage);
        return $percentage;
    }

    /**
     * Encuentra preguntas similares en el historial
     */
    public function findSimilarQuestions(string $message, $userId = null, float $threshold = 60): array
    {
        $history = $this->getConversationHistory($userId);
        $similar = [];

        foreach ($history as $item) {
            if ($item['type'] === 'user') {
                $similarity = $this->getSimilarity($message, $item['message']);
                
                if ($similarity >= $threshold) {
                    $similar[] = [
                        'question' => $item['message'],
                        'answer' => $item['response'],
                        'similarity' => round($similarity, 1),
                        'timestamp' => $item['timestamp'],
                    ];
                }
            }
        }

        // Ordenar por similitud descendente
        usort($similar, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similar, 0, 3); // Top 3
    }

    /**
     * Detecta si es una pregunta de seguimiento (follow-up)
     */
    public function isFollowUp(string $message, $userId = null): bool
    {
        // Palabras que indican follow-up
        $followUpWords = [
            'eso', 'aquello', 'también', 'más', 'y qué', 'pero', 
            'cómo así', 'es decir', 'o sea', 'entonces', 'me explicas',
        ];

        $message = strtolower($message);
        
        foreach ($followUpWords as $word) {
            if (str_contains($message, $word)) {
                $history = $this->getConversationHistory($userId);
                return !empty($history); // Es follow-up si hay historial
            }
        }

        return false;
    }

    /**
     * Genera contexto aumentado para respuesta
     */
    public function getAugmentedContext(string $message, $userId = null): array
    {
        $history = $this->getConversationHistory($userId);
        $lastContext = $this->getLastContext($userId);
        $similarQuestions = $this->findSimilarQuestions($message, $userId, 65);
        $isFollowUp = $this->isFollowUp($message, $userId);

        return [
            'conversation_history' => $this->formatHistoryForAI($history),
            'last_context' => $lastContext,
            'similar_questions' => $similarQuestions,
            'is_followup' => $isFollowUp,
            'conversation_length' => count($history),
            'message_intent' => $this->detectIntent($message),
            'keywords' => $this->extractKeywords($message),
            'suggested_context' => $this->getSuggestedContext($message, $lastContext, $userId),
        ];
    }

    /**
     * Formatea historial para pasarle a la IA
     */
    private function formatHistoryForAI(array $history): string
    {
        if (empty($history)) {
            return "Sin historial anterior en esta conversación.";
        }

        $formatted = "Historial de conversación:\n";
        
        foreach (array_slice($history, -5) as $item) { // Últimos 5 mensajes
            $type = $item['type'] === 'user' ? '👤 Cliente' : '🤖 Bot';
            $formatted .= "\n{$type}: {$item['message']}\n";
        }

        return $formatted;
    }

    /**
     * Sugiere contexto basado en la conversación
     */
    private function getSuggestedContext(string $message, ?array $lastContext, $userId = null): string
    {
        $context = "Contexto relevante: ";

        if ($lastContext && !empty($lastContext['keywords'])) {
            $context .= "El usuario preguntaba sobre " . implode(", ", $lastContext['keywords']) . ". ";
        }

        $similar = $this->findSimilarQuestions($message, $userId, 70);
        
        if (!empty($similar)) {
            $context .= "Preguntas similares recientes del usuario indican interés en " . 
                       implode(", ", array_column($similar, 'question')) . ". ";
        }

        return $context;
    }

    /**
     * Limpia el historial de conversación
     */
    public function clearHistory($userId = null): void
    {
        $key = $this->getSessionKey($userId);
        Session::forget($key);
    }

    /**
     * Obtiene resumen de conversación para análisis
     */
    public function getConversationSummary($userId = null): array
    {
        $history = $this->getConversationHistory($userId);
        
        $summary = [
            'total_messages' => count($history),
            'user_messages' => 0,
            'bot_messages' => 0,
            'main_topics' => [],
            'intents' => [],
            'duration' => null,
        ];

        $allKeywords = [];
        $allIntents = [];

        foreach ($history as $item) {
            if ($item['type'] === 'user') {
                $summary['user_messages']++;
            } else {
                $summary['bot_messages']++;
            }

            if (isset($item['context']['keywords'])) {
                $allKeywords = array_merge($allKeywords, $item['context']['keywords']);
            }

            if (isset($item['context']['intent'])) {
                $allIntents[] = $item['context']['intent'];
            }
        }

        // Temas principales (palabras más repetidas)
        $counts = array_count_values($allKeywords);
        arsort($counts);
        $summary['main_topics'] = array_keys(array_slice($counts, 0, 5));

        // Intenciones más comunes
        $intentCounts = array_count_values($allIntents);
        arsort($intentCounts);
        $summary['intents'] = array_keys(array_slice($intentCounts, 0, 3));

        // Duración aproximada
        if (!empty($history)) {
            $first = strtotime($history[0]['timestamp']);
            $last = strtotime(end($history)['timestamp']);
            $summary['duration'] = round(($last - $first) / 60) . ' minutos';
        }

        return $summary;
    }

    /**
     * Genera prompt mejorado para Gemini con contexto
     */
    public function generateAugmentedPrompt(string $userMessage, string $basePrompt, $userId = null): string
    {
        $augmented = $this->getAugmentedContext($userMessage, $userId);
        
        $prompt = $basePrompt . "\n\n";
        $prompt .= "=== CONTEXTO DE CONVERSACIÓN ===\n";
        $prompt .= $augmented['conversation_history'] . "\n";

        if ($augmented['is_followup']) {
            $prompt .= "\n⚠️ NOTA: Esta es una pregunta de seguimiento (follow-up).\n";
            if ($augmented['last_context']) {
                $prompt .= "Contexto anterior: " . json_encode($augmented['last_context']) . "\n";
            }
        }

        if (!empty($augmented['similar_questions'])) {
            $prompt .= "\n📌 PREGUNTAS SIMILARES PASADAS:\n";
            foreach ($augmented['similar_questions'] as $sim) {
                $prompt .= "- Q: {$sim['question']}\n  A: {$sim['answer']}\n";
            }
        }

        $prompt .= "\n=== NUEVA CONSULTA ===\n";
        $prompt .= "Usuario pregunta: {$userMessage}\n";
        $prompt .= "Intención detectada: {$augmented['message_intent']}\n";
        $prompt .= "Palabras clave: " . implode(", ", $augmented['keywords']) . "\n\n";
        $prompt .= "Responde considerando todo el contexto anterior. Si es seguimiento, enlaza con respuestas pasadas.";

        return $prompt;
    }

    /**
     * Obtiene la clave de sesión para el usuario
     */
    private function getSessionKey($userId = null): string
    {
        $id = $userId ?? auth()->id() ?? 'guest_' . request()->ip();
        return "chatbot_history_{$id}";
    }
}
