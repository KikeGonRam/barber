<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
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

        // Construir el "System Prompt" con los datos reales de tu negocio
        $systemPrompt = $this->buildSystemPrompt($contextData);

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nConsulta del Usuario: " . $userMessage]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7, // Creatividad balanceada
                        'maxOutputTokens' => 150, // Respuestas concisas
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                return "Lo siento, mi conexión neuronal está experimentando interferencias. ¿Podrías preguntar de otra forma?";
            }

            return $response->json('candidates.0.content.parts.0.text') ?? 'No pude generar una respuesta.';

        } catch (\Exception $e) {
            Log::error('Gemini Connection Exception: ' . $e->getMessage());
            return "Error de conexión con el servicio de IA.";
        }
    }

    private function buildSystemPrompt(array $data): string
    {
        // Convertir datos a texto legible para la IA
        $services = implode(", ", $data['services']);
        $barbers = implode(", ", $data['barbers']);
        $userContext = $data['user_name'] ? "El usuario se llama {$data['user_name']} y su rol es {$data['user_role']}." : "El usuario es un visitante no registrado.";
        
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
