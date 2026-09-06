<?php

namespace App\Providers;

use App\Models\User;
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
use App\Services\Chatbot\Contracts\ChatbotAiProvider;
use App\Services\Chatbot\GeminiService;
use App\Services\Chatbot\OllamaService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
        $this->app->bind(InventoryMovementRepositoryInterface::class, InventoryMovementRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);

        // Proveedor de IA del chatbot segun config (ollama local | gemini nube).
        $this->app->bind(ChatbotAiProvider::class, function ($app) {
            return match (config('chatbot.ai.provider')) {
                'ollama' => $app->make(OllamaService::class),
                default => $app->make(GeminiService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        // Blade directive for conditional Vite asset loading
        Blade::directive('safeVite', function ($expression) {
            return "<?php if (file_exists(public_path('build/manifest.json'))) { echo app(\\Illuminate\\Foundation\\Vite::class)($expression); } ?>";
        });

        // Rate Limiter para Login API
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->email.$request->ip());
        });

        // Sin este Gate, Laravel Pulse deniega /pulse a todos por defecto
        // (Gate::authorize('viewPulse') sobre una ability no definida =
        // denegado) -- lo definimos explicitamente para dejar claro que
        // solo administrador/ingeniero pueden verlo, igual que el resto de
        // rutas de solo lectura del rol ingeniero.
        Gate::define('viewPulse', fn (User $user) => $user->hasRoleName('administrador') || $user->hasRoleName('ingeniero'));
    }
}
