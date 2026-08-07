<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\Concerns\BuildsBarberSystemPrompt;
use App\Services\Chatbot\Contracts\ChatbotAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de IA en la nube via Gemini (Google). Requiere GEMINI_API_KEY;
 * si no está configurada, responde en "modo offline" en vez de fallar.
 * Implementa ChatbotAiProvider junto con OllamaService (motor local intercambiable).
 */
class GeminiService implements ChatbotAiProvider
{
    use BuildsBarberSystemPrompt;

    // gemini-1.5-pro fue deprecado — usar v1beta + gemini-2.0-flash
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * True si hay API key configurada (condición para usar este proveedor).
     */
    public function isEnabled(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Etiqueta del modelo para telemetría/logs.
     */
    public function label(): string
    {
        return 'gemini-2.0-flash';
    }

    /**
     * Arma el system prompt y genera una respuesta para un mensaje suelto (sin historial).
     * Efecto secundario: llama a la API externa de Gemini (HTTP).
     */
    public function generateResponse(string $userMessage, array $contextData): string
    {
        if (empty($this->apiKey)) {
            return 'MODO OFFLINE: Para activar mi cerebro de IA, por favor configura la GEMINI_API_KEY en tu archivo .env. Mientras tanto, usaré mi base de conocimientos local.';
        }

        $systemPrompt = $this->buildSystemPrompt($contextData);

        return $this->sendRequest($systemPrompt."\n\nConsulta del Usuario: ".$userMessage);
    }

    /**
     * Genera respuesta con prompt personalizado (util para contexto de conversación)
     */
    public function generateResponseWithPrompt(string $fullPrompt): string
    {
        if (empty($this->apiKey)) {
            return 'MODO OFFLINE: Para activar mi cerebro de IA, por favor configura la GEMINI_API_KEY en tu archivo .env. Mientras tanto, usaré mi base de conocimientos local.';
        }

        return $this->sendRequest($fullPrompt);
    }

    /**
     * Método privado para enviar request a Gemini. Efecto secundario: llamada HTTP externa
     * (con timeout de 15s) y log de errores si falla o hay excepción de conexión.
     */
    private function sendRequest(string $prompt): string
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(15) // corta la espera si Gemini no responde, para no bloquear el request del usuario
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 150,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: '.$response->body());

                return 'Lo siento, mi conexión neuronal está experimentando interferencias. ¿Podrías preguntar de otra forma?';
            }

            return $response->json('candidates.0.content.parts.0.text')
                ?? 'No pude generar una respuesta.';

        } catch (\Exception $e) {
            Log::error('Gemini Connection Exception: '.$e->getMessage());

            return 'Error de conexión con el servicio de IA.';
        }
    }
}
