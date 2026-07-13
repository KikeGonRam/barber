<?php

namespace App\Services\Chatbot\Contracts;

/**
 * Contrato de un proveedor de IA para el chatbot. Permite intercambiar el
 * motor (Gemini en la nube, Ollama local, etc.) sin tocar el controlador.
 */
interface ChatbotAiProvider
{
    /**
     * Si el proveedor esta configurado y listo para responder.
     */
    public function isEnabled(): bool;

    /**
     * Construye el system prompt del concierge a partir del contexto del negocio.
     */
    public function buildSystemPrompt(array $data): string;

    /**
     * Genera una respuesta a partir de un prompt ya armado.
     */
    public function generateResponseWithPrompt(string $fullPrompt): string;

    /**
     * Etiqueta del modelo para telemetria (p. ej. "gemini-2.0-flash" o "ollama:qwen2.5:3b").
     */
    public function label(): string;
}
