<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Barber\BarberController;
use App\Http\Controllers\Campaign\TrackingController;
use App\Http\Controllers\Chatbot\ChatbotController;
use App\Http\Controllers\Client\MembershipController;
use App\Http\Controllers\Dashboard\DatabaseBackupController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Service\ServiceController;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;

// Página de inicio pública: muestra servicios, barberos destacados y estadísticas globales.
Route::get('/', function () {
    // Cache landing page data for 5 minutes — 7 queries → 0 on cache hit
    $services = Cache::remember('landing_services', 300, fn () => Service::where('activo', true)->limit(6)->get()
    );
    $barbers = Cache::remember('landing_barbers', 300, fn () => Barber::with('user')->where('activo', true)->limit(4)->get()
    );
    $statsGlobales = Cache::remember('landing_stats', 300, fn () => [
        'clientes' => Client::count(),
        'servicios' => Service::where('activo', true)->count(),
        'citas' => Appointment::where('estado', 'completada')->count(),
        'rating' => number_format((float) (Comment::whereNotNull('rating')->avg('rating') ?? 4.9), 1),
        'resenas' => Comment::whereNotNull('rating')->count(),
    ]);

    return view('welcome', compact('services', 'barbers', 'statsGlobales'));
})->name('home');

Route::get('/mantenimiento', function () {
    return view('errors.maintenance');
})->name('maintenance');

// Perfil público de un barbero (portafolio, reseñas) y catálogo público de servicios.
Route::get('/equipo/{barber}', [BarberController::class, 'show'])->name('barbers.public.show');
Route::get('/servicios', [ServiceController::class, 'publicIndex'])->name('services.public.index');

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

// Bloque principal de rutas autenticadas: perfil y notificaciones (sin
// equivalente en Nuxt), más lo que sobrevive de cada rol.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneRead'])->name('notifications.read-one');

    // Ruta exclusiva del rol administrador que Nuxt todavía no cubre:
    // respaldo de BD (utilidad, no una pantalla).
    Route::middleware(['verified', 'role.custom:administrador'])->group(function () {
        Route::get('backups/database', [DatabaseBackupController::class, 'download'])->name('backups.database.download');
    });

    // Tarjeta de membresía del cliente (descarga de PDF) — sin equivalente en Nuxt todavía.
    Route::middleware(['verified', 'role.custom:cliente'])->prefix('cliente')->name('client.')->group(function () {
        Route::get('membresia/tarjeta', [MembershipController::class, 'card'])->name('membership.card');
    });
});

require __DIR__.'/auth.php';
