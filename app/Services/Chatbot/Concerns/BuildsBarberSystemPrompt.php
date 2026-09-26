<?php

namespace App\Services\Chatbot\Concerns;

/**
 * System prompt compartido por todos los proveedores de IA del chatbot
 * (Gemini, Ollama, etc.) para que Bladebot responda igual sin importar el motor.
 *
 * El modelo local es pequeño (qwen2.5:0.5b): con un prompt largo y "creativo" inventaba
 * cortes, precios y datos del negocio. Aquí solo van datos reales (catálogo y ficha del
 * negocio) y reglas cortas y concretas.
 */
trait BuildsBarberSystemPrompt
{
    /**
     * Arma el system prompt completo (rol, datos del negocio, contexto de usuario
     * e instrucciones) que se antepone al mensaje del usuario antes de llamar al proveedor de IA.
     */
    public function buildSystemPrompt(array $data): string
    {
        $business = $data['business'] ?? [];
        $name = $business['nombre'] ?? 'UrbanBlade';
        $services = ! empty($data['services']) ? '- '.implode("\n- ", $data['services']) : '- (sin servicios activos)';
        $barbers = implode(', ', $data['barbers'] ?? []) ?: 'consultar en recepción';
        $address = $business['direccion'] ?? 'consultar en recepción';
        $phone = $business['telefono'] ?? 'consultar en recepción';
        $hours = $business['horario'] ?? 'consultar en recepción';
        $cancel = (int) ($business['cancelacion_horas'] ?? 24);
        $userContext = ! empty($data['user_name'])
            ? "El usuario se llama {$data['user_name']} y su rol es {$data['user_role']}."
            : 'El usuario es un visitante no registrado.';
        $extra = $data['extra_context'] ?? '';

        return <<<EOT
Eres Bladebot, el asistente de la barbería {$name} en México. Hablas en español de México, amable y directo.

SERVICIOS (nombre y precio reales, no hay otros):
{$services}

DATOS DEL NEGOCIO:
- Barberos: {$barbers}.
- Dirección: {$address}. Teléfono: {$phone}.
- Horario: {$hours}.
- Pagos: efectivo, transferencia y tarjeta.
- Cancelación sin costo hasta {$cancel} horas antes, desde "Mis citas".

USUARIO:
{$userContext}
{$extra}

REGLAS:
1. Responde en máximo 3 frases cortas (menos de 60 palabras), en texto simple, sin asteriscos, sin títulos y sin listas numeradas.
2. Si recomiendas un servicio, usa exactamente el nombre y el precio de la lista de SERVICIOS; recomienda como máximo dos.
3. No inventes servicios, precios, promociones, productos ni datos del negocio. Si no lo sabes, sugiere preguntar en recepción.
4. Termina siempre con una frase completa.
EOT;
    }
}
