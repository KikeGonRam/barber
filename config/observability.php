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
];
