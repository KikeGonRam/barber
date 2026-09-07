<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

/**
 * Login social (auth-polish-plan, 2026-09-06): solo Google por ahora --
 * Apple Sign-In requiere una cuenta de Apple Developer de pago y
 * verificación de dominio, fuera de alcance sin que el usuario ya la tenga.
 *
 * Sin credenciales reales en GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET (ver
 * config/services.php, .env.example) esta ruta responde con un error claro
 * en vez de romper -- el código queda listo, solo falta que el dueño del
 * proyecto genere sus propias credenciales en Google Cloud Console.
 */
class SocialAuthController extends Controller
{
    /**
     * Redirige al usuario a la pantalla de consentimiento de Google.
     */
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id')) {
            abort(503, 'El login con Google no está configurado todavía.');
        }

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->stateless()->redirect();
    }

    /**
     * Google redirige aquí con el código de autorización. Busca o crea el
     * usuario por email (mismo camino de asignación de rol que
     * AuthController::register() -- siempre 'cliente', nunca algo elegido por
     * el propio flujo de OAuth), emite un token, y manda al usuario de vuelta
     * al frontend con el token en la URL -- mismo patrón que
     * ResetPassword::createUrlUsing() usa frontend_url.
     */
    public function callback(): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url');

        try {
            /** @var AbstractProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
        } catch (Throwable $exception) {
            Log::warning('Login con Google falló al obtener el usuario.', ['error' => $exception->getMessage()]);

            return redirect("{$frontendUrl}/login?error=google_failed");
        }

        $user = User::where('email', $googleUser->getEmail())->first();
        $avatarUrl = $googleUser->getAvatar();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Cliente Google',
                'email' => $googleUser->getEmail(),
                'avatar_url' => $avatarUrl,
                // Contraseña aleatoria: este usuario solo entra por Google, pero
                // el campo es NOT NULL -- nunca se le comunica ni se usa para login normal.
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->markEmailAsVerified();

            $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
            $user->syncRoles([$role]);

            Client::firstOrCreate(['user_id' => $user->id], [
                'preferencias_notificacion' => ['in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false],
            ]);

            event(new Registered($user));
        } elseif (! $user->avatar_url && $avatarUrl) {
            $user->forceFill(['avatar_url' => $avatarUrl])->save();
        }

        $issued = $user->issueMobileApiToken('Google OAuth');

        return redirect("{$frontendUrl}/auth/callback?token={$issued['token']}");
    }
}
