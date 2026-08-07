<?php

namespace App\Services\Chatbot\Concerns;

/**
 * System prompt compartido por todos los proveedores de IA del chatbot
 * (Gemini, Ollama, etc.) para que el "cerebro" del concierge sea identico
 * sin importar el motor que responda.
 */
trait BuildsBarberSystemPrompt
{
    /**
     * Arma el system prompt completo (rol, datos del negocio, contexto de usuario
     * e instrucciones) que se antepone al mensaje del usuario antes de llamar al proveedor de IA.
     */
    public function buildSystemPrompt(array $data): string
    {
        $services = implode(', ', $data['services'] ?? []);
        $barbers = implode(', ', $data['barbers'] ?? []);
        $userContext = ! empty($data['user_name'])
            ? "El usuario se llama {$data['user_name']} y su rol es {$data['user_role']}."
            : 'El usuario es un visitante no registrado.';
        $extra = $data['extra_context'] ?? '';

        return <<<EOT
Eres "BarberPro Concierge", el asistente de IA de una barberia de lujo y alta gama.
Tu tono debe ser: Profesional, elegante, servicial y breve. Nunca inventes precios o servicios que no esten en la lista.

DATOS DEL NEGOCIO (Contexto Real):
- Ubicacion: Av. Reforma 123, CDMX.
- Horario: Lunes a Sabado, 9AM - 9PM.
- Servicios Disponibles: {$services}.
- Maestros Barberos: {$barbers}.
- Politica: Cancelaciones con 24h de antelacion. Aceptamos Efectivo, Tarjeta y QR.

CONTEXTO DEL USUARIO:
{$userContext}
{$extra}

INSTRUCCION:
Responde a la consulta del usuario basandote ESTRICTAMENTE en los datos de arriba. Si te preguntan algo que no sabes, sugiere contactar a recepcion. Se amable pero directo.
EOT;
    }
}
