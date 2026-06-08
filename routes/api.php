<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BarberManagementController;
use App\Http\Controllers\Api\BarberPortfolioController;
use App\Http\Controllers\Api\BarberScheduleController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ChatbotManagementController;
use App\Http\Controllers\Api\ClientController as ApiClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\LogController as ApiLogController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController as ApiReportController;
use App\Http\Controllers\Api\ServiceManagementController as ApiServiceManagementController;
use App\Http\Controllers\Api\SettingController as ApiSettingController;
use App\Http\Controllers\Api\SocialController as ApiSocialController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\WarehouseController as ApiWarehouseController;
use App\Http\Controllers\Api\Admin\DashboardAdminController;
use App\Http\Controllers\Api\Admin\BarberAdminController;
use App\Http\Controllers\Api\Admin\ClientAdminController;
use App\Http\Controllers\Api\Admin\InventoryAdminController;
use App\Http\Controllers\Api\Admin\ReportAdminController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

// Backward compatibility for older frontend cache that still calls /api/availability/slots
Route::get('availability/slots', [AvailabilityController::class, 'slots']);

Route::prefix('v1')->group(function (): void {
    // Public routes
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    
    // Catálogo público
    Route::get('services', [CatalogController::class, 'services']);
    Route::get('barbers', [CatalogController::class, 'barbers']);
    Route::get('availability/slots', [AvailabilityController::class, 'slots']);
    Route::get('social/feed', [ApiSocialController::class, 'feed']);

    // Chatbot (público con rate limiting)
    Route::post('chatbot/query', [ChatbotController::class, 'query'])->middleware('throttle:10,1');

    // Rutas protegidas (requieren token Bearer)
    Route::middleware('mobile.auth')->group(function (): void {
        // Autenticación
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh-token', [AuthController::class, 'refreshToken']);

        // Gestión de Perfil
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::delete('profile', [ProfileController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Citas
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);

        // Pagos (Admin/Recepcionista)
        Route::get('payments', [ApiPaymentController::class, 'index']);
        Route::post('payments', [ApiPaymentController::class, 'store']);
        Route::delete('payments/{payment}', [ApiPaymentController::class, 'destroy']);
        Route::get('payments/{payment}/receipt', [ApiPaymentController::class, 'receipt']);

        // Clientes (Admin/Recepcionista)
        Route::get('clients', [ApiClientController::class, 'index']);
        Route::post('clients', [ApiClientController::class, 'store']);
        Route::put('clients/{client}', [ApiClientController::class, 'update']);
        Route::delete('clients/{client}', [ApiClientController::class, 'destroy']);

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

        // Inventario - Productos (Admin)
        Route::get('inventory/products', [ApiInventoryController::class, 'products']);
        Route::post('inventory/products', [ApiInventoryController::class, 'storeProduct']);
        Route::put('inventory/products/{product}', [ApiInventoryController::class, 'updateProduct']);
        Route::delete('inventory/products/{product}', [ApiInventoryController::class, 'destroyProduct']);

        // Inventario - Movimientos
        Route::get('inventory/movements', [ApiInventoryController::class, 'movements']);
        Route::post('inventory/movements', [ApiInventoryController::class, 'storeMovement']);

        // Warehouse (Legacy)
        Route::get('warehouse', [ApiWarehouseController::class, 'index']);
        Route::post('warehouse', [ApiWarehouseController::class, 'store']);
        Route::put('warehouse/{inventory}', [ApiWarehouseController::class, 'update']);
        Route::delete('warehouse/{inventory}', [ApiWarehouseController::class, 'destroy']);

        // Reportes (Admin/Recepcionista)
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

        // Notificaciones
        Route::get('notifications', [ApiNotificationController::class, 'index']);
        Route::post('notifications/read-all', [ApiNotificationController::class, 'markAllRead']);

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

        // Portafolio de Barbero
        Route::get('barber/portfolio', [BarberPortfolioController::class, 'index']);
        Route::post('barber/works', [BarberPortfolioController::class, 'store']);
        Route::delete('barber/works/{work}', [BarberPortfolioController::class, 'destroy']);

        // Horarios de Barbero
        Route::get('barber/schedule', [BarberScheduleController::class, 'show']);
        Route::put('barber/schedule', [BarberScheduleController::class, 'update']);

        // Admin Dashboard (Phase 1)
        Route::prefix('admin')->middleware('role.custom:administrador')->group(function (): void {
            Route::get('dashboard/stats', [DashboardAdminController::class, 'getStats']);
            Route::get('dashboard/appointments', [DashboardAdminController::class, 'getUpcomingAppointments']);
            Route::get('dashboard/revenue', [DashboardAdminController::class, 'getRevenue']);
            Route::get('dashboard/alerts', [DashboardAdminController::class, 'getAlerts']);
            Route::get('dashboard/metrics', [DashboardAdminController::class, 'getMetrics']);

            // Barberos (Phase 2)
            Route::get('barbers', [BarberAdminController::class, 'getBarbers']);
            Route::get('barbers/{barberId}', [BarberAdminController::class, 'show']);
            Route::get('barbers/{barberId}/schedule', [BarberAdminController::class, 'getSchedule']);
            Route::get('barbers/{barberId}/clients', [BarberAdminController::class, 'getRegularClients']);
            Route::get('barbers/{barberId}/performance', [BarberAdminController::class, 'getPerformanceStats']);
            Route::put('barbers/{barberId}', [BarberAdminController::class, 'update']);

            // Clientes (Phase 2)
            Route::get('clients', [ClientAdminController::class, 'getClients']);
            Route::get('clients/{clientId}', [ClientAdminController::class, 'show']);
            Route::post('clients', [ClientAdminController::class, 'store']);
            Route::put('clients/{clientId}', [ClientAdminController::class, 'update']);
            Route::delete('clients/{clientId}', [ClientAdminController::class, 'destroy']);
            Route::get('clients/segmentation/data', [ClientAdminController::class, 'getSegmentation']);
            Route::get('clients/export', [ClientAdminController::class, 'export']);

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
    });
});
