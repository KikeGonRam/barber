<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tareas programadas (Laravel Scheduler) que corren en segundo plano vía cron.
Schedule::command('appointments:send-reminders')->everyTenMinutes();
Schedule::command('tokens:clean-expired')->daily(); // Limpia tokens de API/reset expirados
Schedule::command('loyalty:draw-raffle')->monthlyOn(1, '08:00'); // Sorteo mensual de fidelización, día 1
Schedule::command('inventory:low-stock-alert')->dailyAt('09:00'); // Notifica productos con stock bajo
Schedule::command('reports:daily-summary')->dailyAt('21:30'); // Resumen diario para administración
Schedule::command('campaigns:dispatch-due')->everyFiveMinutes(); // Envía campañas de correo programadas que ya vencieron
Schedule::command('appointments:mark-no-show')->hourly(); // Marca citas vencidas sin asistencia como "no-show"
Schedule::command('appointments:notify-service-overrun')->everyFiveMinutes(); // Avisa si un servicio se está alargando más de lo esperado
Schedule::command('loyalty:apply-inactivity')->dailyAt('04:00'); // Baja de nivel (180+ días) y caducidad de puntos (365+ días) por inactividad
Schedule::command('barbers:monthly-performance')->monthlyOn(1, '08:30'); // Reporte mensual de desempeño de barberos (mejor mes y caídas fuertes)
Schedule::command('orders:cancel-expired')->dailyAt('05:00'); // Cancela pedidos pendientes no recogidos en 3+ días y devuelve su stock
Schedule::command('clients:send-birthday-greetings')->dailyAt('08:15'); // Felicita y regala puntos a quien cumple años hoy
