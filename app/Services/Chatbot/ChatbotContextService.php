<?php

namespace App\Services\Chatbot;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Session;

/**
 * Mantiene y analiza el historial de conversacion del chatbot en sesion
 * (sin persistencia en BD): detecta intencion, entidades y preguntas de
 * seguimiento en base a heuristicas simples, y arma el contexto aumentado
 * que se inyecta en el prompt enviado al modelo de IA.
 *
 * Además, de forma aditiva y en paralelo (sin cambiar nada del
 * comportamiento de sesión de arriba), persiste cada mensaje en Mongo
 * (ChatMessage) cuando hay un usuario autenticado — ver getPersistedHistory()
 * / getPersistedSummary(), usados por la API (Api\Chatbot\
 * ChatbotManagementController) en vez del historial de sesión, porque un
 * cliente Bearer-token sin cookie (Nuxt) nunca conserva sesión entre
 * requests.
 */
class ChatbotContextService
{
    /**
     * Obtiene el historial de conversación del usuario actual (o invitado,
     * segun IP) desde la sesion -- con fallback a la copia persistida en
     * Mongo (ChatMessage) si la sesión está vacía y hay un usuario
     * autenticado.
     *
     * Encontrado en vivo (2026-09-06): esta era la pieza que faltaba de la
     * Fase 3. Se agregó persistMessage()/getPersistedHistory() para que la
     * API de historial (Nuxt) pudiera leer algo real, pero el MOTOR del
     * chatbot -- findSimilarQuestions(), isFollowUp(), getAugmentedContext(),
     * generateAugmentedPrompt(), todo lo que usa este método -- seguía
     * leyendo únicamente de sesión. Un cliente Bearer-token (Nuxt) nunca
     * comparte sesión entre requests, así que cada mensaje llegaba al motor
     * como si fuera el primero de la conversación: sin memoria de
     * preguntas similares, sin detección de seguimiento, sin nada del
     * intercambio anterior en el prompt de la IA -- "pierde el contexto"
     * en cada turno, aunque el widget sí mostrara el historial completo al
     * reabrirse (esa parte leía de Mongo desde la Fase 3, esta no).
     */
    public function getConversationHistory($userId = null): array
    {
        $key = $this->getSessionKey($userId);
        $history = Session::get($key, []);

        if (empty($history)) {
            $resolvedUserId = $userId ?? auth()->id();

            if ($resolvedUserId) {
                $history = $this->getPersistedHistory((string) $resolvedUserId);
            }
        }

        return $history;
    }

    /**
     * Guarda un mensaje (y su respuesta) en el historial de sesion.
     * Efecto secundario: escribe en sesion y recorta a los ultimos 20
     * mensajes para no hacer crecer la cookie/almacen de sesion sin limite.
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

        $this->persistMessage($message, $response, $type, $userId);
    }

    /**
     * Copia aditiva a Mongo (ChatMessage) del mismo mensaje que ya se
     * guardó en sesión — solo si hay un usuario autenticado (invitados
     * siguen sin persistencia real, mismo comportamiento de antes). No
     * reemplaza la sesión, solo la complementa para clientes sin cookie.
     */
    private function persistMessage(string $message, string $response, string $type, $userId = null): void
    {
        $resolvedUserId = $userId ?? auth()->id();

        if (! $resolvedUserId) {
            return;
        }

        ChatMessage::create([
            'user_id' => (string) $resolvedUserId,
            'message' => $message,
            'response' => $response,
            'type' => $type,
        ]);
    }

    /**
     * Historial persistido en Mongo de un usuario autenticado, en el mismo
     * shape que getConversationHistory() (sin 'context', que solo tiene
     * sentido para el motor de sesión) — para que la API que lo consume no
     * tenga que distinguir entre ambas fuentes.
     */
    public function getPersistedHistory(string $userId, int $limit = 20): array
    {
        return ChatMessage::where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $m) => [
                'timestamp' => optional($m->created_at)->toIso8601String(),
                'type' => $m->type,
                'message' => $m->message,
                'response' => $m->response,
                // Recalculado (no guardado) para que getPersistedSummary()
                // pueda armar main_topics/intents igual que la versión de
                // sesión, sin duplicar esos campos en cada documento Mongo.
                'context' => $this->extractContext($m->message),
            ])
            ->all();
    }

    /**
     * Mismo resumen que getConversationSummary(), pero sobre el historial
     * persistido en Mongo en vez del de sesión.
     */
    public function getPersistedSummary(string $userId): array
    {
        return $this->summarize($this->getPersistedHistory($userId));
    }

    /**
     * Obtiene el contexto (keywords/intent/entities) del ultimo mensaje
     * guardado, o null si no hay historial.
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

        // Extraer referencias a barberos: heuristica simple, nombres
        // hardcodeados (no viene de la BD de barberos reales).
        if (str_contains($message, 'carlos') || str_contains($message, 'juan') || str_contains($message, 'luis')) {
            $entities['barbers'][] = strtolower(substr($message, strpos($message, 'c')));
        }

        return $entities;
    }

    /**
     * Calcula similitud entre dos preguntas (0-100) usando similar_text de
     * PHP (comparacion de caracteres, no semantica).
     */
    public function getSimilarity(string $message1, string $message2): float
    {
        $similarity = similar_text($message1, $message2, $percentage);

        return $percentage;
    }

    /**
     * Busca en el historial preguntas previas del usuario (mismo mensaje
     * type='user') con similitud >= threshold, para reforzar contexto.
     */
    public function findSimilarQuestions(string $message, $userId = null, float $threshold = 60): array
    {
        $history = $this->getConversationHistory($userId);
        $similar = [];

        // Cada entrada del historial ya representa un turno completo
        // (pregunta + respuesta juntas, ver addMessage()) -- 'type' nunca
        // vale 'user' en ningún lugar del código (siempre se guarda como
        // 'bot', porque quien "escribe" la entrada es el bot al terminar
        // de responder). El filtro `type === 'user'` de abajo nunca
        // coincidía con nada desde siempre: esta rama de "memoria
        // instantánea" (la más rápida de toda la cascada de
        // ChatbotController::query()) jamás se activaba, ni para el widget
        // Blade ni para nadie. Se compara contra 'message' en cada
        // entrada, sin filtrar por tipo.
        foreach ($history as $item) {
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

        // Ordenar por similitud descendente
        usort($similar, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similar, 0, 3); // Top 3
    }

    /**
     * Detecta si es una pregunta de seguimiento (follow-up) por presencia
     * de palabras de continuidad, solo si ya hay historial previo.
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

                return ! empty($history); // Es follow-up si hay historial
            }
        }

        return false;
    }

    /**
     * Arma el contexto aumentado (historial formateado, ultimo contexto,
     * preguntas similares, intencion, keywords) que alimenta el prompt de
     * la IA para dar respuestas coherentes con la conversacion.
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
            return 'Sin historial anterior en esta conversación.';
        }

        $formatted = "Historial de conversación:\n";

        // Cada entrada ya es un turno completo (pregunta del cliente +
        // respuesta del bot juntas, ver addMessage()) -- 'type' siempre
        // vale 'bot' (nunca 'user', en ningún lugar del código), así que
        // el `$item['type'] === 'user' ? 'Cliente' : 'Bot'` de antes
        // etiquetaba SIEMPRE como "Bot" el mensaje del propio CLIENTE
        // (item['message']) y nunca incluía la respuesta real del bot
        // (item['response']) -- el historial que se le mandaba a la IA
        // como contexto tenía la conversación mal atribuida y a medias.
        foreach (array_slice($history, -5) as $item) { // Últimos 5 turnos
            $formatted .= "\nCliente: {$item['message']}\nBot: {$item['response']}\n";
        }

        return $formatted;
    }

    /**
     * Sugiere contexto basado en la conversación
     */
    private function getSuggestedContext(string $message, ?array $lastContext, $userId = null): string
    {
        $context = 'Contexto relevante: ';

        if ($lastContext && ! empty($lastContext['keywords'])) {
            $context .= 'El usuario preguntaba sobre '.implode(', ', $lastContext['keywords']).'. ';
        }

        $similar = $this->findSimilarQuestions($message, $userId, 70);

        if (! empty($similar)) {
            $context .= 'Preguntas similares recientes del usuario indican interés en '.
                       implode(', ', array_column($similar, 'question')).'. ';
        }

        return $context;
    }

    /**
     * Limpia el historial de conversación. Efecto secundario: borra la
     * clave de sesion correspondiente, y — si hay usuario autenticado —
     * también su historial persistido en Mongo (ChatMessage), para que
     * "limpiar historial" desde la API/Nuxt de verdad limpie lo que esa
     * misma API lee (getPersistedHistory()), no solo la sesión que Nuxt
     * nunca usa.
     */
    public function clearHistory($userId = null): void
    {
        $key = $this->getSessionKey($userId);
        Session::forget($key);

        $resolvedUserId = $userId ?? auth()->id();

        if ($resolvedUserId) {
            ChatMessage::where('user_id', (string) $resolvedUserId)->delete();
        }
    }

    /**
     * Obtiene resumen de conversación para análisis (conteos, temas
     * principales, intenciones frecuentes, duracion aproximada).
     */
    public function getConversationSummary($userId = null): array
    {
        return $this->summarize($this->getConversationHistory($userId));
    }

    /**
     * Cuerpo compartido de getConversationSummary()/getPersistedSummary() —
     * ambas arman el mismo resumen, solo cambia de dónde viene $history
     * (sesión vs. Mongo).
     */
    private function summarize(array $history): array
    {
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

        // Mismo motivo que findSimilarQuestions()/formatHistoryForAI() de
        // arriba: cada entrada ya es un turno completo (una pregunta del
        // cliente + una respuesta del bot), así que ambos contadores
        // avanzan juntos -- no tiene sentido filtrar por 'type' (siempre
        // 'bot') para repartir el conteo entre los dos.
        foreach ($history as $item) {
            $summary['user_messages']++;
            $summary['bot_messages']++;

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
        if (! empty($history)) {
            $first = strtotime($history[0]['timestamp']);
            $last = strtotime(end($history)['timestamp']);
            $summary['duration'] = round(($last - $first) / 60).' minutos';
        }

        return $summary;
    }

    /**
     * Genera prompt mejorado con contexto (agnostico del proveedor de IA:
     * sirve igual para Ollama/qwen local o Gemini). No tiene efectos
     * secundarios propios, pero llama a getAugmentedContext que lee sesion.
     */
    public function generateAugmentedPrompt(string $userMessage, string $basePrompt, $userId = null): string
    {
        $augmented = $this->getAugmentedContext($userMessage, $userId);

        $prompt = $basePrompt."\n\n";
        $prompt .= "=== CONTEXTO DE CONVERSACIÓN ===\n";
        $prompt .= $augmented['conversation_history']."\n";

        if ($augmented['is_followup']) {
            $prompt .= "\nNOTA: Esta es una pregunta de seguimiento (follow-up).\n";
            if ($augmented['last_context']) {
                $prompt .= 'Contexto anterior: '.json_encode($augmented['last_context'])."\n";
            }
        }

        if (! empty($augmented['similar_questions'])) {
            $prompt .= "\nPREGUNTAS SIMILARES PASADAS:\n";
            foreach ($augmented['similar_questions'] as $sim) {
                $prompt .= "- Q: {$sim['question']}\n  A: {$sim['answer']}\n";
            }
        }

        $prompt .= "\n=== NUEVA CONSULTA ===\n";
        $prompt .= "Usuario pregunta: {$userMessage}\n";
        $prompt .= "Intención detectada: {$augmented['message_intent']}\n";
        $prompt .= 'Palabras clave: '.implode(', ', $augmented['keywords'])."\n\n";
        $prompt .= 'Responde considerando todo el contexto anterior. Si es seguimiento, enlaza con respuestas pasadas.';

        return $prompt;
    }

    /**
     * Obtiene la clave de sesión para el usuario. Si no hay usuario
     * autenticado ni id explicito, usa la IP para dar continuidad a
     * invitados sin cuenta.
     */
    private function getSessionKey($userId = null): string
    {
        $id = $userId ?? auth()->id() ?? 'guest_'.request()->ip();

        return "chatbot_history_{$id}";
    }
}
