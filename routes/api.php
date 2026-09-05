<?php

use App\Http\Controllers\Api\Admin\Barber\BarberAdminController;
use App\Http\Controllers\Api\Admin\Client\ClientAdminController;
use App\Http\Controllers\Api\Admin\Dashboard\DashboardAdminController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryAdminController;
use App\Http\Controllers\Api\Admin\Report\ReportAdminController;
use App\Http\Controllers\Api\Appointment\AppointmentController;
use App\Http\Controllers\Api\Appointment\AvailabilityController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Barber\BarberManagementController;
use App\Http\Controllers\Api\Barber\BarberPortfolioController;
use App\Http\Controllers\Api\Barber\BarberScheduleController;
use App\Http\Controllers\Api\Catalog\CatalogController;
use App\Http\Controllers\Api\Chatbot\ChatbotManagementController;
use App\Http\Controllers\Api\Client\ClientController as ApiClientController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Inventory\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\Log\LogController as ApiLogController;
use App\Http\Controllers\Api\Notification\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\Payment\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\Payment\StripeWebhookController;
use App\Http\Controllers\Api\Prediction\PredictionController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Report\ReportController as ApiReportController;
use App\Http\Controllers\Api\Service\ServiceManagementController as ApiServiceManagementController;
use App\Http\Controllers\Api\Setting\SettingController as ApiSettingController;
use App\Http\Controllers\Api\Social\SocialController as ApiSocialController;
use App\Http\Controllers\Api\User\UserController as ApiUserController;
use App\Http\Controllers\Chatbot\ChatbotController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Rutas fuera del prefijo v1: integraciones externas y compatibilidad retro.
// Stripe webhook — sin auth ni CSRF; Stripe valida con firma HMAC
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([PreventRequestForgery::class]);

// Backward compatibility for older frontend cache that still calls /api/availability/slots
Route::get('availability/slots', [AvailabilityController::class, 'slots'])
    ->middleware('throttle:30,1');

// API móvil (app cliente/barbero/admin), versionada bajo /v1. Autenticación por token
// Bearer (middleware 'mobile.auth'), no por sesión web.
Route::prefix('v1')->group(function (): void {
    // Rutas públicas: login, registro, recuperación de contraseña y catálogo (sin token).
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    // Catálogo público
    Route::get('services', [CatalogController::class, 'services']);
    Route::get('barbers', [CatalogController::class, 'barbers']);
    Route::get('availability/slots', [AvailabilityController::class, 'slots'])->middleware('throttle:30,1');
    Route::get('social/feed', [ApiSocialController::class, 'feed']);

    // Chatbot (público con rate limiting)
    Route::post('chatbot/query', [ChatbotController::class, 'query'])->middleware('throttle:10,1');

    // Rutas protegidas (requieren token Bearer): disponibles para cualquier usuario
    // autenticado; los sub-grupos de abajo añaden restricción por rol.
    Route::middleware('mobile.auth')->group(function (): void {
        // Autenticación
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);

        // Gestión de Perfil
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('profile/push-token', [ProfileController::class, 'savePushToken']);
        Route::delete('profile', [ProfileController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Citas
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::get('appointments/calendar-data', [AppointmentController::class, 'calendarData']);
        Route::get('appointments/chargeable', [AppointmentController::class, 'chargeable']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);

        // Solo administrador y recepcionista: gestión de pagos, clientes e inventario.
        Route::middleware('role.custom:administrador,recepcionista')->group(function (): void {
            // Pagos (Admin/Recepcionista)
            Route::get('payments', [ApiPaymentController::class, 'index']);
            Route::get('payments/pending', [ApiPaymentController::class, 'pending']);
            Route::post('payments', [ApiPaymentController::class, 'store']);
            Route::post('payments/stripe-intent', [ApiPaymentController::class, 'stripeIntent'])->name('api.payments.stripe-intent');
            Route::post('payments/{payment}/approve', [ApiPaymentController::class, 'approve']);
            Route::post('payments/{payment}/reject', [ApiPaymentController::class, 'reject']);
            Route::delete('payments/{payment}', [ApiPaymentController::class, 'destroy']);
            Route::get('payments/{payment}/receipt', [ApiPaymentController::class, 'receipt']);

            // Clientes (Admin/Recepcionista)
            Route::get('clients', [ApiClientController::class, 'index']);
            Route::post('clients', [ApiClientController::class, 'store']);
            Route::put('clients/{client}', [ApiClientController::class, 'update']);
            Route::delete('clients/{client}', [ApiClientController::class, 'destroy']);

            // Inventario (Admin/Recepcionista; acciones sensibles se refuerzan en controller)
            Route::get('inventory/products', [ApiInventoryController::class, 'products']);
            Route::post('inventory/products', [ApiInventoryController::class, 'storeProduct']);
            Route::put('inventory/products/{product}', [ApiInventoryController::class, 'updateProduct']);
            Route::delete('inventory/products/{product}', [ApiInventoryController::class, 'destroyProduct']);
            Route::get('inventory/movements', [ApiInventoryController::class, 'movements']);
            Route::post('inventory/movements', [ApiInventoryController::class, 'storeMovement']);
        });

        // Solo administrador: servicios, barberos, usuarios, reportes, configuración y logs.
        Route::middleware('role.custom:administrador')->group(function (): void {
            // Servicios (Admin)
            Route::get('services/manage', [ApiServiceManagementController::class, 'index']);
            Route::post('services/manage', [ApiServiceManagementController::class, 'store']);
            Route::put('services/manage/{service}', [ApiServiceManagementController::class, 'update']);
            Route::delete('services/manage/{service}', [ApiServiceManagementController::class, 'destroy']);

            // Barberos (Admin)
            Route::get('barbers/manage', [BarberManagementController::class, 'index']);
            Route::put('barbers/manage/{barber}', [BarberManagementController::class, 'update']);

            // Usuarios (Admin)
            Route::get('users', [ApiUserController::class, 'index']);
            Route::post('users', [ApiUserController::class, 'store']);
            Route::put('users/{user}', [ApiUserController::class, 'update']);
            Route::delete('users/{user}', [ApiUserController::class, 'destroy']);

            // Reportes (Admin)
            Route::get('reports', [ApiReportController::class, 'index']);
            Route::get('reports/{type}/{format}', [ApiReportController::class, 'export'])
                ->whereIn('type', ['ingresos', 'citas', 'inventario', 'clientes'])
                ->whereIn('format', ['json', 'pdf', 'excel']);

            // Configuración (Admin)
            Route::get('settings', [ApiSettingController::class, 'show']);
            Route::put('settings', [ApiSettingController::class, 'update']);
            Route::post('settings/maintenance', [ApiSettingController::class, 'toggleMaintenance']);

            // Logs (Admin)
            Route::get('logs', [ApiLogController::class, 'index']);
        });

        // Notificaciones
        Route::get('notifications', [ApiNotificationController::class, 'index']);
        Route::post('notifications/read-all', [ApiNotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [ApiNotificationController::class, 'markOneRead']);
        Route::delete('notifications/{id}', [ApiNotificationController::class, 'destroy']);

        // Social
        Route::post('social/work/{work}/react', [ApiSocialController::class, 'react']);
        Route::post('social/work/{work}/save', [ApiSocialController::class, 'save']);
        Route::post('social/work/{work}/comment', [ApiSocialController::class, 'comment']);

        // Chatbot - Gestión (Historial, Perfil, Estadísticas)
        Route::get('chatbot/history', [ChatbotManagementController::class, 'getHistory']);
        Route::post('chatbot/clear-history', [ChatbotManagementController::class, 'clearHistory']);
        Route::get('chatbot/profile', [ChatbotManagementController::class, 'getProfile']);
        Route::get('chatbot/learning-stats', [ChatbotManagementController::class, 'getLearningStats']);
        Route::post('chatbot/train-history', [ChatbotManagementController::class, 'trainFromHistory'])
            ->middleware('role.custom:administrador');

        // Solo barbero: su propio perfil/bio, portafolio y horario de trabajo.
        Route::middleware('role.custom:barbero')->group(function (): void {
            // Bio/perfil propio del Barbero
            Route::get('barber/me', [ProfileController::class, 'showBarberProfile']);
            Route::get('barber/bio', [ProfileController::class, 'showBarberBio']);
            Route::put('barber/bio', [ProfileController::class, 'updateBarberBio']);

            // Portafolio de Barbero
            Route::get('barber/portfolio', [BarberPortfolioController::class, 'index']);
            Route::post('barber/works', [BarberPortfolioController::class, 'store']);
            Route::delete('barber/works/{work}', [BarberPortfolioController::class, 'destroy']);

            // Horarios de Barbero
            Route::get('barber/schedule', [BarberScheduleController::class, 'show']);
            Route::put('barber/schedule', [BarberScheduleController::class, 'update']);
        });

        // Panel de administración móvil completo (dashboard, barberos, clientes,
        // inventario, reportes y predicciones/insights de IA). Solo administrador.
        Route::prefix('admin')->middleware('role.custom:administrador')->group(function (): void {
            Route::get('dashboard/stats', [DashboardAdminController::class, 'getStats']);
            Route::get('dashboard/appointments', [DashboardAdminController::class, 'getUpcomingAppointments']);
            Route::get('dashboard/revenue', [DashboardAdminController::class, 'getRevenue']);
            Route::get('dashboard/alerts', [DashboardAdminController::class, 'getAlerts']);
            Route::get('dashboard/metrics', [DashboardAdminController::class, 'getMetrics']);

            // Barberos (Phase 2)
            Route::get('barbers', [BarberAdminController::class, 'getBarbers']);
            Route::get('barbers/{barber}', [BarberAdminController::class, 'show']);
            Route::get('barbers/{barber}/schedule', [BarberAdminController::class, 'getSchedule']);
            Route::get('barbers/{barber}/clients', [BarberAdminController::class, 'getRegularClients']);
            Route::get('barbers/{barber}/performance', [BarberAdminController::class, 'getPerformanceStats']);
            Route::put('barbers/{barber}', [BarberAdminController::class, 'update']);

            // Clientes (Phase 2)
            Route::get('clients', [ClientAdminController::class, 'getClients']);
            Route::get('clients/segmentation/data', [ClientAdminController::class, 'getSegmentation']);
            Route::get('clients/export', [ClientAdminController::class, 'export']);
            Route::get('clients/{client}', [ClientAdminController::class, 'show']);
            Route::post('clients', [ClientAdminController::class, 'store']);
            Route::put('clients/{client}', [ClientAdminController::class, 'update']);
            Route::delete('clients/{client}', [ClientAdminController::class, 'destroy']);

            // Inventario (Phase 3)
            Route::get('inventory/products', [InventoryAdminController::class, 'getProducts']);
            Route::get('inventory/products/{productId}', [InventoryAdminController::class, 'show']);
            Route::post('inventory/products', [InventoryAdminController::class, 'store']);
            Route::put('inventory/products/{productId}', [InventoryAdminController::class, 'update']);
            Route::delete('inventory/products/{productId}', [InventoryAdminController::class, 'destroy']);
            Route::post('inventory/products/{productId}/movement', [InventoryAdminController::class, 'recordMovement']);
            Route::get('inventory/movements', [InventoryAdminController::class, 'getMovements']);
            Route::get('inventory/summary', [InventoryAdminController::class, 'getSummary']);
            Route::get('inventory/low-stock', [InventoryAdminController::class, 'getLowStockProducts']);

            // Reportes (Phase 3)
            Route::get('reports/revenue', [ReportAdminController::class, 'generateRevenueReport']);
            Route::get('reports/appointments', [ReportAdminController::class, 'generateAppointmentsReport']);
            Route::get('reports/inventory', [ReportAdminController::class, 'generateInventoryReport']);
            Route::get('reports/clients', [ReportAdminController::class, 'generateClientsReport']);
            Route::post('reports/custom', [ReportAdminController::class, 'generateCustomReport']);
            Route::get('reports/list', [ReportAdminController::class, 'listReports']);
            Route::get('reports/export', [ReportAdminController::class, 'exportReport']);

            // Predicciones e Insights (IA)
            Route::get('predictions/income/{days?}', [PredictionController::class, 'incomeForecasting']);
            Route::get('predictions/appointments/{days?}', [PredictionController::class, 'appointmentForecast']);
            Route::get('predictions/services', [PredictionController::class, 'serviceAnalysis']);
            Route::get('predictions/peak-hours', [PredictionController::class, 'peakHoursAnalysis']);
            Route::get('predictions/insights', [PredictionController::class, 'insights']);
        });

        // Detalle de barbero y reseñas (van después de barbers/manage para no chocar con el wildcard)
        Route::get('barbers/{barber}', [CatalogController::class, 'showBarber']);
        Route::post('barbers/{barber}/review', [CatalogController::class, 'storeReview']);
    });
});
