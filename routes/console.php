<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tareas programadas (Laravel Scheduler) que corren en segundo plano vía cron.
// ->description() le da un nombre estable a cada tarea, usado por
// ScheduledTaskMonitor (App\Services\System) para asociar sus eventos
// ScheduledTaskFinished/Failed con la tarea correcta -- sin esto, el nombre
// visible en esos eventos sería el comando shell completo (con ruta al
// binario de PHP y redirecciones), no un identificador limpio.
Schedule::command('appointments:send-reminders')->everyTenMinutes()->description('appointments:send-reminders');
Schedule::command('tokens:clean-expired')->daily()->description('tokens:clean-expired'); // Limpia tokens de API/reset expirados
Schedule::command('loyalty:draw-raffle')->monthlyOn(1, '08:00')->description('loyalty:draw-raffle'); // Sorteo mensual de fidelización, día 1
Schedule::command('inventory:low-stock-alert')->dailyAt('09:00')->description('inventory:low-stock-alert'); // Notifica productos con stock bajo
Schedule::command('reports:daily-summary')->dailyAt('21:30')->description('reports:daily-summary'); // Resumen diario para administración
Schedule::command('campaigns:dispatch-due')->everyFiveMinutes()->description('campaigns:dispatch-due'); // Envía campañas de correo programadas que ya vencieron
Schedule::command('appointments:mark-no-show')->hourly()->description('appointments:mark-no-show'); // Marca citas vencidas sin asistencia como "no-show"
Schedule::command('appointments:notify-service-overrun')->everyFiveMinutes()->description('appointments:notify-service-overrun'); // Avisa si un servicio se está alargando más de lo esperado
Schedule::command('loyalty:apply-inactivity')->dailyAt('04:00')->description('loyalty:apply-inactivity'); // Baja de nivel (180+ días) y caducidad de puntos (365+ días) por inactividad
Schedule::command('barbers:monthly-performance')->monthlyOn(1, '08:30')->description('barbers:monthly-performance'); // Reporte mensual de desempeño de barberos (mejor mes y caídas fuertes)
Schedule::command('orders:cancel-expired')->dailyAt('05:00')->description('orders:cancel-expired'); // Cancela pedidos pendientes no recogidos en 3+ días y devuelve su stock
Schedule::command('clients:send-birthday-greetings')->dailyAt('08:15')->description('clients:send-birthday-greetings'); // Felicita y regala puntos a quien cumple años hoy
