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
    | CORS_ALLOWED_ORIGIN_PATTERNS (regex, comma-separated) is for origins
    | whose subdomain changes on every deploy (e.g. preview deployments).
    | Empty by default since 2026-10-02: the web is served from AWS
    | (urbanblade.com.mx) and no longer from Vercel, so the old default that
    | accepted any frontend-urbanblade-*.vercel.app deployment was removed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))),

    'allowed_origins_patterns' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Con 0 el navegador no guarda el preflight (OPTIONS) y repite una petición extra ANTES de
    // cada llamada de Nuxt a la API (otro dominio, con Authorization): en una pantalla con 10
    // llamadas son 10 viajes de ida y vuelta de más. 7200 s es el tope que respetan todos los
    // navegadores (Chrome limita a 2 h). Se puede ajustar con CORS_MAX_AGE (0 lo desactiva).
    'max_age' => (int) env('CORS_MAX_AGE', 7200),

    'supports_credentials' => false,

];
