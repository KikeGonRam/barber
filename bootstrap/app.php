<?php

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
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
        $middleware->alias([
            'role.custom' => \App\Http\Middleware\Role\EnsureUserHasRole::class,
            'permission.custom' => \App\Http\Middleware\Role\EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, $request) {
            $domainExceptions = [
                \App\Exceptions\Domain\AppointmentConflictException::class,
                \App\Exceptions\Domain\InsufficientStockException::class,
                \App\Exceptions\Domain\PaymentException::class,
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
