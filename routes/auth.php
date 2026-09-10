<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailCodeController;
use Illuminate\Support\Facades\Route;

// Páginas de login/registro/recuperación: migradas a Nuxt el 2026-09-09.
// frontend-urban ya tenía su propio flujo completo contra Api\Auth\
// AuthController (login/registro/forgot-password/reset-password) y
// Api\Auth\SocialAuthController (Google) desde antes -- nunca dependió de
// este archivo. El enlace de recuperación de contraseña por correo YA
// apuntaba directo a Nuxt (ResetPassword::createUrlUsing() en
// AppServiceProvider), así que reset-password/{token} de aquí abajo es solo
// una red de seguridad para un email/bookmark viejo, no el flujo real.
// Verificación de email: los usuarios registrados vía API se marcan
// verificados automáticamente (AuthController::register(), comentario
// "Mark email as verified for mobile app") -- Nuxt nunca necesitó una
// pantalla de verificación. Rutas con nombre conservadas (no borradas) para
// no romper route('login')/route('register') usados por el redirect por
// defecto del middleware 'auth' y por Route::middleware('guest').
Route::get('register', fn () => redirect(config('app.frontend_url').'/register'))->name('register');
Route::get('login', fn () => redirect(config('app.frontend_url').'/login'))->name('login');
Route::get('forgot-password', fn () => redirect(config('app.frontend_url').'/forgot-password'))->name('password.request');
Route::get('reset-password/{token}', fn (string $token) => redirect(config('app.frontend_url').'/reset-password?token='.$token))->name('password.reset');

// Endpoints POST originales de Breeze: sin página Blade que los invoque
// desde arriba, pero se dejan vivos (no se borran) por si algún bookmark o
// integración vieja todavía les manda un submit directo.
Route::middleware('guest')->group(function () {
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Rutas para usuarios ya autenticados: verificación de email (por código),
// confirmación de contraseña, cambio de contraseña y logout.
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('verify-email/code', [VerifyEmailCodeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.verify-code');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
