<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | Explicitly configured (rather than left at the framework's '*' default)
    | because a separate origin — the Nuxt frontend in ../frontend-urban —
    | now calls api/* directly from the browser. Auth is Bearer-token based
    | (see routes/api.php's mobile.auth group), not cookie/session based, so
    | supports_credentials stays false and there is no CSRF/cookie exposure
    | even while allowed_origins is broad in local dev. CORS_ALLOWED_ORIGINS
    | in .env is a comma-separated list; set it to the real Nuxt prod domain
    | once deployed instead of relying on the dev default below.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
