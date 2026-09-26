<?php

namespace App\Services\Chatbot;

use App\Services\Ai\LocalAi;
use App\Services\Chatbot\Concerns\BuildsBarberSystemPrompt;
use App\Services\Chatbot\Contracts\ChatbotAiProvider;

/**
 * Proveedor de IA local via Ollama. Corre 100% en la maquina (sin API key ni
 * costo). Se conecta a un servidor Ollama que expone /api/generate.
 * Implementa ChatbotAiProvider junto con GeminiService (motor en la nube
 * intercambiable); esta es la opcion sin costo para desarrollo/produccion sin API key.
 */
class OllamaService implements ChatbotAiProvider
{
    use BuildsBarberSystemPrompt;

    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        // Sin barra final para evitar // al concatenar la ruta.
        $this->baseUrl = rtrim((string) config('chatbot.ai.ollama.url', 'http://host.docker.internal:11434'), '/');
        $this->model = (string) config('chatbot.ai.ollama.model', 'qwen2.5:3b');
        $this->timeout = (int) config('chatbot.ai.ollama.timeout', 90);
    }

    /**
     * True si hay URL de servidor y modelo configurados (no verifica conectividad real).
     */
    public function isEnabled(): bool
    {
        // Con el cortacircuito abierto (IA caída o lenta) ni se intenta: respuesta sin IA al instante.
        return $this->baseUrl !== '' && $this->model !== '' && app(LocalAi::class)->available();
    }

    /**
     * Etiqueta del modelo para telemetría/logs.
     */
    public function label(): string
    {
        return 'ollama:'.$this->model;
    }

    /**
     * Envía el prompt ya armado al modelo local por LocalAi (límite de tiempo y cortacircuito
     * compartidos). Devuelve '' si la IA no respondió a tiempo: el controlador entonces contesta
     * con la respuesta sin IA, en vez de mostrarle al cliente un error de conexión.
     */
    public function generateResponseWithPrompt(string $fullPrompt): string
    {
        return app(LocalAi::class)->complete($fullPrompt, 160, (float) $this->timeout, 0.5) ?? '';
    }
}
