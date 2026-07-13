<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\Concerns\BuildsBarberSystemPrompt;
use App\Services\Chatbot\Contracts\ChatbotAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de IA local via Ollama. Corre 100% en la maquina (sin API key ni
 * costo). Se conecta a un servidor Ollama que expone /api/generate.
 */
class OllamaService implements ChatbotAiProvider
{
    use BuildsBarberSystemPrompt;

    private string $baseUrl;

    private string $model;

    private int $timeout;

    private string $keepAlive;

    public function __construct()
    {
        // Sin barra final para evitar // al concatenar la ruta.
        $this->baseUrl = rtrim((string) config('chatbot.ai.ollama.url', 'http://host.docker.internal:11434'), '/');
        $this->model = (string) config('chatbot.ai.ollama.model', 'qwen2.5:3b');
        $this->timeout = (int) config('chatbot.ai.ollama.timeout', 90);
        // Mantiene el modelo residente en (V)RAM entre peticiones: la primera
        // carga en frio tarda decenas de segundos, pero ya caliente responde
        // en ~1s. Evita recargar el modelo en cada consulta.
        $this->keepAlive = (string) config('chatbot.ai.ollama.keep_alive', '30m');
    }

    public function isEnabled(): bool
    {
        return $this->baseUrl !== '' && $this->model !== '';
    }

    public function label(): string
    {
        return 'ollama:'.$this->model;
    }

    public function generateResponseWithPrompt(string $fullPrompt): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $fullPrompt,
                    'stream' => false,
                    'keep_alive' => $this->keepAlive,
                    'options' => [
                        'temperature' => 0.7,
                        // Limita la longitud de la respuesta (concierge breve).
                        'num_predict' => 220,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Ollama API Error: '.$response->body());

                return 'Lo siento, mi conexion neuronal esta experimentando interferencias. Podrias preguntar de otra forma?';
            }

            $text = trim((string) $response->json('response', ''));

            return $text !== '' ? $text : 'No pude generar una respuesta.';
        } catch (\Throwable $e) {
            Log::error('Ollama Connection Exception: '.$e->getMessage());

            return 'Error de conexion con el servicio de IA local.';
        }
    }
}
