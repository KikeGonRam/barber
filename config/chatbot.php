<?php

return [
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
