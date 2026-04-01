<?php

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\PaymentException;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\Role\EnsureUserHasPermission;
use App\Http\Middleware\Role\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CheckMaintenanceMode::class,
        ]);
        $middleware->alias([
            'role.custom' => EnsureUserHasRole::class,
            'permission.custom' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
