<?php

/*
 * Bloqueo automático de IPs que mandan demasiadas peticiones en pocos segundos (equivalente a
 * fail2ban a nivel de aplicación). Ver App\Http\Middleware\BlockAbusiveIps.
 */
return [
    'enabled' => (bool) env('ABUSE_BLOCK_ENABLED', true),

    // Peticiones permitidas por IP dentro de una ventana de N segundos. 300 en 10 s son 30 por
    // segundo sostenidos: muy por encima de un visitante, de la app o de las páginas que la web
    // renderiza en servidor.
    'window_seconds' => (int) env('ABUSE_WINDOW_SECONDS', 10),
    'max_requests' => (int) env('ABUSE_MAX_REQUESTS', 300),

    // Primer bloqueo; cada reincidencia dentro de 24 h lo duplica hasta el máximo.
    'ban_seconds' => (int) env('ABUSE_BAN_SECONDS', 600),
    'max_ban_seconds' => (int) env('ABUSE_MAX_BAN_SECONDS', 86400),

    // IPs que nunca se bloquean (lista separada por comas): monitoreo propio, la red de la escuela...
    'allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('ABUSE_ALLOWLIST', ''))))),

    // Las IPs privadas y de loopback (chequeos de salud, desarrollo local) no se cuentan. Solo las
    // pruebas lo apagan.
    'skip_private' => (bool) env('ABUSE_SKIP_PRIVATE', true),
];
