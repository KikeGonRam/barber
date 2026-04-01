<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    // gemini-1.5-pro fue deprecado — usar v1beta + gemini-2.0-flash
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generateResponse(string $userMessage, array $contextData): string
    {
        if (empty($this->apiKey)) {
            return "MODO OFFLINE: Para activar mi cerebro de IA, por favor configura la GEMINI_API_KEY en tu archivo .env. Mientras tanto, usaré mi base de conocimientos local.";
        }

        $systemPrompt = $this->buildSystemPrompt($contextData);
        return $this->sendRequest($systemPrompt . "\n\nConsulta del Usuario: " . $userMessage);
    }

    /**
     * Genera respuesta con prompt personalizado (util para contexto de conversación)
     */
    public function generateResponseWithPrompt(string $fullPrompt): string
    {
        if (empty($this->apiKey)) {
            return "MODO OFFLINE: Para activar mi cerebro de IA, por favor configura la GEMINI_API_KEY en tu archivo .env. Mientras tanto, usaré mi base de conocimientos local.";
        }

        return $this->sendRequest($fullPrompt);
    }

    /**
     * Método privado para enviar request a Gemini
     */
    private function sendRequest(string $prompt): string
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(15)
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 150,
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                return "Lo siento, mi conexión neuronal está experimentando interferencias. ¿Podrías preguntar de otra forma?";
            }

            return $response->json('candidates.0.content.parts.0.text')
                ?? 'No pude generar una respuesta.';

        } catch (\Exception $e) {
            Log::error('Gemini Connection Exception: ' . $e->getMessage());
            return "Error de conexión con el servicio de IA.";
        }
    }

    public function buildSystemPrompt(array $data): string
    {
        $services     = implode(", ", $data['services']);
        $barbers      = implode(", ", $data['barbers']);
        $userContext  = $data['user_name']
            ? "El usuario se llama {$data['user_name']} y su rol es {$data['user_role']}."
            : "El usuario es un visitante no registrado.";
        
        return <<<EOT
Eres "BarberPro Concierge", el asistente de IA de una barbería de lujo y alta gama.
Tu tono debe ser: Profesional, elegante, servicial y breve. Nunca inventes precios o servicios que no estén en la lista.

DATOS DEL NEGOCIO (Contexto Real):
- Ubicación: Av. Reforma 123, CDMX.
- Horario: Lunes a Sábado, 9AM - 9PM.
- Servicios Disponibles: {$services}.
- Maestros Barberos: {$barbers}.
- Política: Cancelaciones con 24h de antelación. Aceptamos Efectivo, Tarjeta y QR.

CONTEXTO DEL USUARIO:
{$userContext}
{$data['extra_context']}

INSTRUCCIÓN:
Responde a la consulta del usuario basándote ESTRICTAMENTE en los datos de arriba. Si te preguntan algo que no sabes, sugiere contactar a recepción. Se amable pero directo.
EOT;
    }
}
