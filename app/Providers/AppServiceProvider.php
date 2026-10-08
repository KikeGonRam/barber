<?php

namespace App\Providers;

use App\Listeners\EmbedMailLogo;
use App\Models\User;
use App\Notifications\Auth\WelcomeNotification;
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
use App\Services\Mail\MailConfigCheck;
use App\Services\System\QueueFailureMonitor;
use App\Services\System\ScheduledTaskMonitor;
use App\Support\DataEnvironmentGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
     * En producción, deja una advertencia en el log (una vez por hora) si el correo no está configurado para
     * entregar mensajes de verdad. Sin MAIL_* Laravel usa el mailer "log" y la app responde "enviado" sin
     * mandar nada: pasó en staging y no daba ningún error. Nunca rompe el arranque.
     */
    private function warnIfMailIsNotDelivering(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        try {
            $mailer = (string) config('mail.default');
            $config = (array) config("mail.mailers.{$mailer}", []);
            $problems = MailConfigCheck::problems(
                $mailer,
                isset($config['host']) ? (string) $config['host'] : null,
                (string) config('mail.from.address'),
                isset($config['username']) ? (string) $config['username'] : null,
            );

            if ($problems !== [] && Cache::add('mail-config-warning', true, now()->addHour())) {
                Log::warning('Correo saliente sin configurar: los correos NO están llegando a los usuarios.', ['problemas' => $problems]);
            }
        } catch (\Throwable) {
            // Es solo un aviso: si falla (p. ej. la caché), no debe tumbar la aplicación.
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->warnIfMailIsNotDelivering();

        // Logo incrustado en todos los correos (imagen cid:), para que se vea aunque el cliente bloquee imágenes remotas.
        Event::listen(MessageSending::class, EmbedMailLogo::class);

        DataEnvironmentGuard::assertSafe(
            (string) config('app.env'),
            (string) config('database.data_environment'),
            (string) config('database.connections.mongodb.dsn'),
            (string) config('database.connections.mongodb.database'),
        );

        // Detras de CloudFront/ALB el contenedor recibe HTTP plano (el ALB
        // reescribe X-Forwarded-Proto a "http"), asi que sin esto asset(),
        // redirects y URLs firmadas saldrian como http:// y el navegador las
        // bloquearia como contenido mixto en un sitio HTTPS. Se activa solo
        // cuando APP_URL ya es https, asi que local (http) no cambia.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        // Blade directive for conditional Vite asset loading
        Blade::directive('safeVite', function ($expression) {
            return "<?php if (file_exists(public_path('build/manifest.json'))) { echo app(\\Illuminate\\Foundation\\Vite::class)($expression); } ?>";
        });

        // Rate Limiter para Login API
        RateLimiter::for('login', function (Request $request) {
            // Solo si el correo es texto: un arreglo («email": [...]) tronaba aquí con 500 antes de validar (T054).
            $email = is_string($request->input('email')) ? mb_strtolower($request->input('email')) : '';

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        // Sin este Gate, Laravel Pulse deniega /pulse a todos por defecto
        // (Gate::authorize('viewPulse') sobre una ability no definida =
        // denegado) -- lo definimos explicitamente para dejar claro que
        // solo administrador/ingeniero pueden verlo, igual que el resto de
        // rutas de solo lectura del rol ingeniero.
        Gate::define('viewPulse', fn (User $user) => $user->hasRoleName('administrador') || $user->hasRoleName('ingeniero'));

        // Alimenta ScheduledTaskMonitor (dashboard del rol ingeniero) con la
        // última corrida de cada tarea de routes/console.php, sin tener que
        // instrumentar cada Schedule::command() una por una.
        Event::listen(ScheduledTaskFinished::class, [ScheduledTaskMonitor::class, 'recordFinished']);
        Event::listen(ScheduledTaskFailed::class, [ScheduledTaskMonitor::class, 'recordFailed']);

        // failed_jobs conserva el payload para reintentos; este listener deja
        // ademas una senal operativa minima y sin PII en logs/Sentry.
        Event::listen(JobFailed::class, [QueueFailureMonitor::class, 'recordFailed']);

        // Correo de bienvenida al crear la cuenta (registro o primer inicio con Google). Un fallo
        // del correo nunca debe tumbar el alta: se registra y se sigue.
        Event::listen(Registered::class, function (Registered $event): void {
            try {
                if ($event->user instanceof User) {
                    $event->user->notify(new WelcomeNotification);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el correo de bienvenida.', ['error' => $e->getMessage()]);
            }
        });

        // Password::sendResetLink() (AuthController::forgotPassword()) usa esta
        // notificación con su URL por defecto, que apunta a la ruta web
        // 'password.reset' (la página Blade) -- sin esto, un usuario que pide
        // el reset desde el frontend Nuxt terminaría en la pantalla vieja sin
        // importar desde dónde lo pidió. FRONTEND_URL ya se usa con el mismo
        // criterio en otras rutas retiradas.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $email = urlencode($user->getEmailForPasswordReset());

            return config('app.frontend_url')."/reset-password?token={$token}&email={$email}";
        });

        // Correo de restablecer contraseña con la marca y en español (antes salía con la plantilla en inglés de
        // Laravel: «Reset your password», «Hello!», «Regards»). El enlace sigue yendo a la web: el token no pasa por la app.
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
            $email = urlencode($user->getEmailForPasswordReset());

            $url = config('app.frontend_url')."/reset-password?token={$token}&email={$email}";

            $mail = (new MailMessage)
                ->subject('Restablece tu contraseña — UrbanBlade')
                ->markdown('emails.message', [
                    'accent' => '#d4af37',
                    'badge' => 'Seguridad',
                    'title' => 'Restablece tu contraseña',
                    'greeting' => 'Hola '.$user->name.',',
                    'intro' => "Recibimos una solicitud para restablecer la contraseña de tu cuenta. El enlace vence en {$minutes} minutos.",
                    'ctaLabel' => 'Restablecer contraseña',
                    'ctaUrl' => $url,
                    'secondary' => 'Si no fuiste tú, ignora este correo: tu contraseña no cambia.',
                ]);

            // Se conserva el contrato de MailMessage (quien lo inspecciona, p. ej. las pruebas, lee el enlace aquí).
            $mail->actionText = 'Restablecer contraseña';
            $mail->actionUrl = $url;

            return $mail;
        });
    }
}
