<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\BarberManagementController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ClientController as ApiClientController;
use App\Http\Controllers\Api\LogController as ApiLogController;
use App\Http\Controllers\Api\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\ReportController as ApiReportController;
use App\Http\Controllers\Api\ServiceManagementController as ApiServiceManagementController;
use App\Http\Controllers\Api\SettingController as ApiSettingController;
use App\Http\Controllers\Api\SocialController as ApiSocialController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\WarehouseController as ApiWarehouseController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);

    Route::get('services', [CatalogController::class, 'services']);
    Route::get('barbers', [CatalogController::class, 'barbers']);
    Route::get('availability/slots', [AvailabilityController::class, 'slots']);
    Route::get('social/feed', [ApiSocialController::class, 'feed']);

    Route::middleware('mobile.auth')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('notifications', [ApiNotificationController::class, 'index']);
        Route::post('notifications/read-all', [ApiNotificationController::class, 'markAllRead']);

        Route::post('social/work/{work}/react', [ApiSocialController::class, 'react']);
        Route::post('social/work/{work}/save', [ApiSocialController::class, 'save']);
        Route::post('social/work/{work}/comment', [ApiSocialController::class, 'comment']);

        Route::get('users', [ApiUserController::class, 'index']);
        Route::post('users', [ApiUserController::class, 'store']);
        Route::put('users/{user}', [ApiUserController::class, 'update']);
        Route::delete('users/{user}', [ApiUserController::class, 'destroy']);

        Route::get('clients', [ApiClientController::class, 'index']);
        Route::post('clients', [ApiClientController::class, 'store']);
        Route::put('clients/{client}', [ApiClientController::class, 'update']);
        Route::delete('clients/{client}', [ApiClientController::class, 'destroy']);

        Route::get('services/manage', [ApiServiceManagementController::class, 'index']);
        Route::post('services/manage', [ApiServiceManagementController::class, 'store']);
        Route::put('services/manage/{service}', [ApiServiceManagementController::class, 'update']);
        Route::delete('services/manage/{service}', [ApiServiceManagementController::class, 'destroy']);

        Route::get('inventory/products', [ApiInventoryController::class, 'products']);
        Route::post('inventory/products', [ApiInventoryController::class, 'storeProduct']);
        Route::put('inventory/products/{product}', [ApiInventoryController::class, 'updateProduct']);
        Route::delete('inventory/products/{product}', [ApiInventoryController::class, 'destroyProduct']);

        Route::get('inventory/movements', [ApiInventoryController::class, 'movements']);
        Route::post('inventory/movements', [ApiInventoryController::class, 'storeMovement']);

        Route::get('warehouse', [ApiWarehouseController::class, 'index']);
        Route::post('warehouse', [ApiWarehouseController::class, 'store']);
        Route::put('warehouse/{inventory}', [ApiWarehouseController::class, 'update']);
        Route::delete('warehouse/{inventory}', [ApiWarehouseController::class, 'destroy']);

        Route::get('payments', [ApiPaymentController::class, 'index']);
        Route::post('payments', [ApiPaymentController::class, 'store']);
        Route::delete('payments/{payment}', [ApiPaymentController::class, 'destroy']);
        Route::get('payments/{payment}/receipt', [ApiPaymentController::class, 'receipt']);

        Route::get('logs', [ApiLogController::class, 'index']);

        Route::get('barbers/manage', [BarberManagementController::class, 'index']);
        Route::put('barbers/manage/{barber}', [BarberManagementController::class, 'update']);

        Route::get('settings', [ApiSettingController::class, 'show']);
        Route::put('settings', [ApiSettingController::class, 'update']);
        Route::post('settings/maintenance', [ApiSettingController::class, 'toggleMaintenance']);

        Route::get('reports', [ApiReportController::class, 'index']);
        Route::get('reports/{type}/{format}', [ApiReportController::class, 'export'])
            ->whereIn('type', ['ingresos', 'citas', 'inventario', 'clientes'])
            ->whereIn('format', ['json', 'pdf', 'excel']);

        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);

        Route::get('chatbot/history', [ChatbotController::class, 'getHistory']);
        Route::get('chatbot/profile', [ChatbotController::class, 'getProfile']);
        Route::post('chatbot/clear-history', [ChatbotController::class, 'clearHistory']);
        Route::get('chatbot/learning-stats', [ChatbotController::class, 'getLearningStats']);
        Route::post('chatbot/train-history', [ChatbotController::class, 'trainFromHistory']);
    });

    Route::post('chatbot/query', [ChatbotController::class, 'query']);
});