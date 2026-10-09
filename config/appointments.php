<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Margen para iniciar una cita (minutos)
    |--------------------------------------------------------------------------
    |
    | El barbero puede pasar una cita a «en proceso» desde este número de minutos antes de su
    | hora de inicio (el cliente suele llegar un poco antes). Nunca antes del día de la cita.
    |
    */
    'start_margin_minutes' => (int) env('APPOINTMENT_START_MARGIN_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Tiempo extra de un servicio en curso
    |--------------------------------------------------------------------------
    |
    | Minutos que el barbero puede agregar de una vez (aviso «tu servicio termina en 5 min») y el máximo acumulado
    | por cita. Ver ServiceTimeService.
    |
    */
    'extension_options' => [10, 15, 20, 30],
    'max_extra_minutes' => (int) env('APPOINTMENT_MAX_EXTRA_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Exigir que el cliente acepte el cargo por inasistencia al reservar
    |--------------------------------------------------------------------------
    |
    | Se activa cuando la web y la app ya muestran la casilla en el último paso de la reserva; antes, las
    | versiones que no la envían dejarían de poder reservar. Si el cliente la envía, siempre se registra.
    |
    */
    'require_no_show_acceptance' => (bool) env('APPOINTMENT_REQUIRE_NO_SHOW_ACCEPTANCE', false),

];
