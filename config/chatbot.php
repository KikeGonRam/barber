<?php

return [
    'rate_limit' => [
        'max_attempts' => env('CHATBOT_RATE_LIMIT_MAX_ATTEMPTS', 20),
        'decay_seconds' => env('CHATBOT_RATE_LIMIT_DECAY_SECONDS', 60),
    ],
];