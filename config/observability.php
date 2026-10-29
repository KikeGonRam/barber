<?php

/*
 * Observabilidad de rendimiento. Cuando una petición o una consulta a MongoDB pasa de su umbral se escribe
 * una advertencia en el log (stderr, que ECS manda a CloudWatch). Con eso se ve qué ruta o qué colección frena
 * a la API sin adivinar. Un umbral en -1 apaga el aviso correspondiente.
 */
return [
    // Petición HTTP completa, en milisegundos.
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 2000),

    // Un solo comando a MongoDB (find, aggregate, update...), en milisegundos.
    'slow_query_ms' => (int) env('SLOW_QUERY_MS', 300),

    // Métricas de tráfico por minuto para el monitor del ingeniero (peticiones, errores 5xx, latencia media).
    // Apagadas por defecto: requieren un almacén de caché Redis o Memcached (ver RequestMetrics::enabled).
    'metrics_enabled' => (bool) env('METRICS_ENABLED', false),

    // Fracción de peticiones que se cuentan (0.01 a 1). Las cifras se reescalan, así que son aproximadas.
    'metrics_sample_rate' => (float) env('METRICS_SAMPLE_RATE', 0.2),

    // Almacén de caché de los contadores; vacío usa el predeterminado.
    'metrics_cache_store' => env('METRICS_CACHE_STORE'),
];
