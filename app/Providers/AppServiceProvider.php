<?php

namespace App\Providers;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\InventoryMovementRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\ServiceRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ... (repositorios)
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('safeVite', function ($expression) {
            return "<?php if (file_exists(public_path('build/manifest.json'))) { echo app(\\Illuminate\\Foundation\\Vite::class)($expression); } ?>";
        });

        // 🛡️ HARDENING: Rate Limiter para Login API
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->email.$request->ip());
        });
    }
}
