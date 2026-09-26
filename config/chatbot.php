<?php

return [
    // Proveedor de IA del ultimo recurso del chatbot (cascada: memoria -> BD ->
    // reglas -> datos externos -> IA). Intercambiable sin tocar el controlador.
    'ai' => [
        // 'ollama' (local, sin costo) | 'gemini' (nube, requiere GEMINI_API_KEY)
        'provider' => env('CHATBOT_AI_PROVIDER', 'gemini'),

        'ollama' => [
            // El contenedor llega al Ollama del host via host.docker.internal.
            'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
            // Red de seguridad para la primera carga en frio del modelo (~decenas
            // de segundos con poca RAM libre). Ya caliente responde en ~1s.
            // Segundos que Bladebot espera al modelo antes de contestar sin IA.
            'timeout' => env('OLLAMA_TIMEOUT', 15),
            // Tras un fallo o una espera de más, minutos sin intentar la IA (ver App\Services\Ai\LocalAi).
            'cooldown_seconds' => env('OLLAMA_COOLDOWN_SECONDS', 120),
            // Cuanto mantener el modelo cargado en (V)RAM entre consultas.
            'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
        ],
    ],

    'rate_limit' => [
        'max_attempts' => env('CHATBOT_RATE_LIMIT_MAX_ATTEMPTS', 20),
        'decay_seconds' => env('CHATBOT_RATE_LIMIT_DECAY_SECONDS', 60),
    ],
    'telemetry' => [
        'enabled' => env('CHATBOT_TELEMETRY_ENABLED', true),
        'sample_rate' => env('CHATBOT_TELEMETRY_SAMPLE_RATE', 1),
        'ai_cost_per_1k_tokens' => env('CHATBOT_TELEMETRY_AI_COST_PER_1K_TOKENS', 0.00035),
    ],
];
