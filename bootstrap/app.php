<?php

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\PaymentException;
use App\Http\Middleware\AuthenticateMobileApiToken;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Role\EnsureUserHasPermission;
use App\Http\Middleware\Role\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/v1/auth/get-api-token',
            '_boost/browser-logs',
        ]);
        $middleware->web(append: [
            CheckMaintenanceMode::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role.custom' => EnsureUserHasRole::class,
            'permission.custom' => EnsureUserHasPermission::class,
            'mobile.auth' => AuthenticateMobileApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sin esto Sentry no reporta las excepciones no capturadas -- el DSN
        // solo no basta en Laravel 11+ (el pipeline de excepciones cambio).
        // Si SENTRY_LARAVEL_DSN esta vacio, el SDK simplemente no envia nada.
        Integration::handles($exceptions);

        $exceptions->render(function (Throwable $exception, $request) {
            $domainExceptions = [
                AppointmentConflictException::class,
                InsufficientStockException::class,
                PaymentException::class,
            ];

            foreach ($domainExceptions as $domainException) {
                if ($exception instanceof $domainException) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => $exception->getMessage(),
                        ], 422);
                    }

                    return back()->withInput()->withErrors([
                        'general' => $exception->getMessage(),
                    ]);
                }
            }

            return null;
        });
    })->create();
