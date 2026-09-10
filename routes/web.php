<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Campaign\TrackingController;
use App\Http\Controllers\Chatbot\ChatbotController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Barber;

// Landing pública: migrada a Nuxt el 2026-09-09. frontend-urban ya tenía su
// propia landing completa (app/pages/index.vue) desde antes -- de hecho más
// completa que welcome.blade.php (respeta los 4 temas, no solo negro fijo).
// Redirect público: nadie necesita sesión para ver la landing. Ruta con
// nombre conservada (no borrada) para no romper route('home').
Route::get('/', fn () => redirect(config('app.frontend_url')))->name('home');

Route::get('/mantenimiento', function () {
    return view('errors.maintenance');
})->name('maintenance');

// Perfil público de un barbero y catálogo de servicios: migrados a Nuxt
// (frontend-urban) el 2026-09-09 -- /equipo(/[slug]) y /servicios ahí tienen
// paridad completa (mismo CatalogController via API, GET barbers/{barber} ya
// público). Rutas con nombre conservadas (no borradas) para no romper
// route('services.public.index')/route('barbers.public.show', ...), usados
// en welcome.blade.php y TrackingController -- ver la lección de 2026-09-06
// documentada en urbanblade-guardrails.
Route::get('/equipo/{barber}', fn (Barber $barber) => redirect(config('app.frontend_url').'/equipo/'.$barber->slug))->name('barbers.public.show');
Route::get('/servicios', fn () => redirect(config('app.frontend_url').'/servicios'))->name('services.public.index');

// Seguimiento de campanas (publico: los golpea el cliente de correo).
Route::get('/t/o/{campaign}/{user}', [TrackingController::class, 'open'])->name('track.open');
Route::get('/t/c/{campaign}/{user}', [TrackingController::class, 'click'])->name('track.click');
Route::post('/chatbot/query', [ChatbotController::class, 'query'])->middleware('throttle:20,1')->name('chatbot.query');
// Rutas del chatbot que requieren sesión (historial, perfil, estadísticas de aprendizaje).
// Cualquier usuario autenticado puede usarlas; solo "train-history" se restringe a administrador.
Route::middleware(['auth'])->group(function () {
    Route::get('/chatbot/history', [ChatbotController::class, 'getHistory'])->name('chatbot.history');
    Route::get('/chatbot/profile', [ChatbotController::class, 'getProfile'])->name('chatbot.profile');
    Route::post('/chatbot/clear-history', [ChatbotController::class, 'clearHistory'])->name('chatbot.clear-history');
    Route::get('/chatbot/learning-stats', [ChatbotController::class, 'getLearningStats'])->name('chatbot.learning-stats');
    Route::post('/chatbot/train-history', [ChatbotController::class, 'trainFromHistory'])
        ->middleware(['verified', 'role.custom:administrador', 'throttle:3,1'])
        ->name('chatbot.train-history');
});

// Antes renderizaba Inertia\Vue (ver .claude/skills/inertia-vue-migration/SKILL.md);
// retirado porque Nuxt (frontend-urban) ya tiene los 4 dashboards por rol con
// paridad funcional confirmada. Sin middleware 'auth' a propósito: Nuxt
// gestiona su propia sesión (Bearer token, no cookie de Laravel), así que
// decidir si el usuario puede ver el dashboard le toca a su propio middleware,
// no a esta redirección.
Route::get('/dashboard', fn () => redirect(config('app.frontend_url').'/dashboard'))
    ->name('dashboard');

// El resto del panel administrativo/staff/cliente/barbero (citas, calendario,
// clientes, pagos, pedidos, inventario, servicios, usuarios, barberos,
// campañas, sorteos, reportes, configuración, logs, analítica, y todo el
// autoservicio de cliente/barbero) se retiró de Blade: Nuxt (frontend-urban)
// tiene paridad funcional confirmada para cada una de esas páginas (Fases
// 1-9 + Analítica, ver frontend-urban/.claude/skills/nuxt-migration-plan/
// SKILL.md). Lo que queda abajo NO tiene equivalente en Nuxt todavía.
Route::get('/appointments-calendar', fn () => redirect(config('app.frontend_url').'/appointments/calendar'))
    ->middleware(['auth', 'verified'])
    ->name('appointments.calendar');

// Web-session based API token retrieval — ya no lo usa ninguna página Blade
// desde que el dashboard se movió a Nuxt, pero se deja vivo (no es una
// "página") por si algún flujo web futuro necesita este puente sesión→token.
Route::post('/api/v1/auth/get-api-token', [AuthController::class, 'getWebApiToken'])
    ->middleware(['web', 'auth', 'throttle:20,1'])
    ->name('api.get-token');

// Página de notificaciones y sus preferencias: migradas a Nuxt el
// 2026-09-09 (mismo Api\Notification\NotificationController via API,
// paridad completa). Redirect público (no requiere sesión web -- Nuxt
// resuelve su propia auth con el token Bearer) para que el enlace del pie
// de los correos (route('notifications.preferences') en
// vendor/mail/html/message.blade.php) y el ícono del topbar
// (notification-toaster.blade.php) sigan funcionando.
Route::get('/notifications', fn () => redirect(config('app.frontend_url').'/notifications'))->name('notifications.index');
Route::get('/notifications/preferences', fn () => redirect(config('app.frontend_url').'/notifications'))->name('notifications.preferences');

// Página de perfil: migrada a Nuxt el 2026-09-09 (/profile ahí ya cubre
// edición, avatar, contraseña y borrado de cuenta contra el mismo
// Api\Profile\ProfileController). Redirect público: Nuxt resuelve su propia
// auth con el token Bearer.
Route::get('/profile', fn () => redirect(config('app.frontend_url').'/profile'))->name('profile.edit');

// Bloque principal de rutas autenticadas: los endpoints PATCH/DELETE de
// perfil y los endpoints AJAX de notificaciones que siguen usando el
// toaster global (notification-toaster.blade.php) en las pantallas que aún
// son Blade (paneles de staff), más lo que sobrevive de cada rol. Ninguno
// de estos es una "página" -- no hay Blade que los redirija, solo formularios
// ya huérfanos desde que /profile redirige (ver arriba).
Route::middleware('auth')->group(function () {
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneRead'])->name('notifications.read-one');
});

// Respaldo de BD y tarjeta de membresía: migrados a Nuxt el 2026-09-09
// (GET /api/v1/system/backup, GET /api/v1/dashboard/membership/card, ambos
// via token Bearer -- ver App\Services\System\DatabaseBackupService /
// App\Services\Member\MemberCardService::cardPdfData()). Redirigen a la
// pantalla de Nuxt donde vive el botón real, no al archivo binario -- ya no
// hace falta sesión web para llegar aquí.
Route::get('backups/database', fn () => redirect(config('app.frontend_url').'/settings'))->name('backups.database.download');
Route::prefix('cliente')->name('client.')->group(function () {
    Route::get('membresia/tarjeta', fn () => redirect(config('app.frontend_url').'/dashboard'))->name('membership.card');
});

require __DIR__.'/auth.php';
