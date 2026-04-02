<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ClientController as ApiClientController;
use App\Http\Controllers\Api\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);

    Route::get('services', [CatalogController::class, 'services']);
    Route::get('barbers', [CatalogController::class, 'barbers']);
    Route::get('availability/slots', [AvailabilityController::class, 'slots']);

    Route::middleware('mobile.auth')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('users', [ApiUserController::class, 'index']);
        Route::get('clients', [ApiClientController::class, 'index']);

        Route::get('inventory/products', [ApiInventoryController::class, 'products']);
        Route::get('inventory/movements', [ApiInventoryController::class, 'movements']);

        Route::get('payments', [ApiPaymentController::class, 'index']);
        Route::post('payments', [ApiPaymentController::class, 'store']);

        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
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