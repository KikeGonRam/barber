<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use MongoDB\Driver\Exception\BulkWriteException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

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
     *
     * ?target=spark hace que callback() regrese al dashboard de analítica
     * (spark, Streamlit) en vez de al frontend del cliente. Viaja como
     * `state` -- Google lo devuelve intacto en el callback, y no requiere
     * una redirect_uri nueva en Google Cloud Console porque la URL de
     * callback registrada (esta misma ruta) no cambia.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! config('services.google.client_id')) {
            abort(503, 'El login con Google no está configurado todavía.');
        }

        $target = $request->query('target') === 'spark' ? 'spark' : 'frontend';

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->stateless()->with(['state' => $target])->redirect();
    }

    /**
     * Google redirige aquí con el código de autorización. Busca o crea el
     * usuario por email (mismo camino de asignación de rol que
     * AuthController::register() -- siempre 'cliente', nunca algo elegido por
     * el propio flujo de OAuth), emite un token, y manda al usuario de vuelta
     * al destino indicado por `state` (frontend del cliente, o spark) con el
     * token en la URL -- mismo patrón que ResetPassword::createUrlUsing()
     * usa frontend_url. spark hace su propia verificación de rol
     * (administrador/ingeniero) llamando a GET /api/v1/auth/me con este
     * token -- este controlador no filtra por rol, solo acredita identidad.
     */
    public function callback(Request $request): RedirectResponse
    {
        $target = $request->query('state') === 'spark' ? 'spark' : 'frontend';
        $frontendUrl = config('app.frontend_url');
        $sparkUrl = config('app.spark_url');
        $errorUrl = $target === 'spark' ? "{$sparkUrl}/?google_error=1" : "{$frontendUrl}/login?error=google_failed";

        try {
            /** @var AbstractProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
        } catch (Throwable $exception) {
            Log::warning('Login con Google falló al obtener el usuario.', ['error' => $exception->getMessage()]);

            return redirect($errorUrl);
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $name = $googleUser->getName() ?: $googleUser->getNickname() ?: 'Cliente Google';

        try {
            $user = $this->findOrCreateGoogleUser($email, $name, $googleUser->getAvatar());
        } catch (RuntimeException) {
            Log::warning('Colisión de correo durante login con Google; se requiere reintento.');

            return redirect($target === 'spark' ? "{$sparkUrl}/?google_error=retry" : "{$frontendUrl}/login?error=google_retry");
        }

        $issued = $user->issueMobileApiToken($target === 'spark' ? 'Google OAuth (spark)' : 'Google OAuth');

        if ($target === 'spark') {
            return redirect("{$sparkUrl}/?google_token={$issued['token']}");
        }

        return redirect("{$frontendUrl}/auth/callback?token={$issued['token']}");
    }

    /**
     * Login con Google Nativo (Android)
     *
     * Verifica un ID token emitido por Google Identity Services / Credential
     * Manager directamente (sin pasar por el navegador ni el flujo
     * authorization-code de redirect()/callback() de arriba) y emite el
     * mismo Bearer token que el resto de la API. Pensado para la app Android
     * nativa: Credential Manager entrega un ID token firmado por Google, no
     * un código de autorización, así que no puede reusar Socialite.
     *
     * @unauthenticated
     *
     * @bodyParam id_token string required El ID token emitido por Google Identity Services. Example: eyJhbGciOiJSUzI1NiIs...
     *
     * @response {
     *  "message": "Autenticación exitosa.",
     *  "token_type": "Bearer",
     *  "token": "1|abc123def456...",
     *  "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com" }
     * }
     * @response 401 {
     *  "message": "El token de Google no es válido."
     * }
     */
    public function token(Request $request): JsonResponse
    {
        if (! config('services.google.client_id')) {
            abort(503, 'El login con Google no está configurado todavía.');
        }

        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $claims = $this->verifyGoogleIdToken($validated['id_token']);
        } catch (Throwable $exception) {
            Log::warning('Login nativo con Google falló al verificar el ID token.', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'El token de Google no es válido.'], 401);
        }

        $email = Str::lower(trim((string) ($claims->email ?? '')));
        $name = (string) ($claims->name ?? 'Cliente Google');
        $avatarUrl = $claims->picture ?? null;

        if ($email === '' || ! ($claims->email_verified ?? false)) {
            return response()->json(['message' => 'El token de Google no es válido.'], 401);
        }

        try {
            $user = $this->findOrCreateGoogleUser($email, $name, $avatarUrl);
        } catch (RuntimeException) {
            return response()->json(['message' => 'No se pudo completar el inicio de sesión, intenta de nuevo.'], 409);
        }

        $issued = $user->issueMobileApiToken('Google Android');

        return response()->json([
            'message' => 'Autenticación exitosa.',
            'token_type' => 'Bearer',
            'token' => $issued['token'],
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Verifica la firma, expiración, audiencia y emisor de un ID token de
     * Google contra sus llaves públicas (JWKS, cacheadas unas horas -- Google
     * las rota pero no tan seguido como para golpear su endpoint en cada
     * login). JWT::decode() ya valida firma+exp; aud/iss se checan a mano
     * porque decode() no sabe qué audiencia/emisor esperamos.
     */
    private function verifyGoogleIdToken(string $idToken): object
    {
        $jwks = Cache::remember('google_jwks', now()->addHours(6), function () {
            return Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/certs')->throw()->json();
        });

        $claims = JWT::decode($idToken, JWK::parseKeySet($jwks));

        $validIssuers = ['accounts.google.com', 'https://accounts.google.com'];
        if (! in_array($claims->iss ?? null, $validIssuers, true)) {
            throw new UnexpectedValueException('Emisor del token inesperado.');
        }

        if (($claims->aud ?? null) !== config('services.google.client_id')) {
            throw new UnexpectedValueException('Audiencia del token inesperada.');
        }

        return $claims;
    }

    /**
     * Busca o crea el usuario a partir de una identidad ya acreditada por
     * Google (mismo camino de asignación de rol que AuthController::register()
     * -- siempre 'cliente', nunca algo elegido por el propio flujo de OAuth).
     * Compartido por el flujo web (callback()) y el nativo (token()) para que
     * no diverjan.
     *
     * @throws RuntimeException si dos logins concurrentes chocan y ni así se
     *                          encuentra el usuario recién creado (llamador decide cómo responder).
     */
    private function findOrCreateGoogleUser(string $email, string $name, ?string $avatarUrl): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        // Un usuario eliminado lógicamente sigue ocupando su correo en el
        // índice único de MongoDB. Google ya validó que la persona controla
        // ese correo, por lo que se recupera su misma cuenta sin crear una
        // paralela ni perder el perfil/historial asociado.
        if ($user?->trashed()) {
            $user->restore();
        }

        if (! $user) {
            // Google solo acredita la identidad. El primer alta pública puede
            // bootstrapear al administrador SOLO si el flag está habilitado
            // (config/auth.php: deshabilitado por defecto salvo APP_ENV=local,
            // y explícitamente false en .env.example incluso ahí -- mismo gate
            // que AuthController::register(), para que Google no sea una vía
            // paralela sin el mismo candado). Un login posterior nunca cambia
            // el rol existente.
            $canBootstrapAdmin = User::withTrashed()->count() === 0
                && (bool) config('auth.first_user_admin_enabled', false);

            try {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    // Contraseña aleatoria: este usuario solo entra por Google, pero
                    // el campo es NOT NULL -- nunca se le comunica ni se usa para login normal.
                    'password' => Hash::make(Str::random(40)),
                ]);
                $user->markEmailAsVerified();

                $role = Role::firstOrCreate([
                    'name' => $canBootstrapAdmin ? 'administrador' : 'cliente',
                    'guard_name' => 'web',
                ]);
                $user->syncRoles([$role]);

                if (! $canBootstrapAdmin) {
                    Client::firstOrCreate(['user_id' => $user->id], [
                        'preferencias_notificacion' => ['in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false],
                    ]);
                }

                event(new Registered($user));
            } catch (BulkWriteException $exception) {
                // Dos requests (dos pestañas, o web+Android casi a la vez)
                // pueden terminar casi a la vez. El segundo debe reutilizar
                // la cuenta recién creada, no responder 500.
                if ($exception->getCode() !== 11000) {
                    throw $exception;
                }

                $user = User::where('email', $email)->first();

                if (! $user) {
                    throw new RuntimeException('Colisión de correo durante login con Google.');
                }
            }
        }

        if (! $user->avatar_url || $this->isGoogleAvatarUrl($user->avatar_url) || $this->isStaleStoredAvatar($user->avatar_url)) {
            $importedAvatar = $this->importGoogleAvatar($user, $avatarUrl);

            if ($importedAvatar) {
                $user->forceFill(['avatar_url' => $importedAvatar])->save();
            }
        }

        return $user;
    }

    private function importGoogleAvatar(User $user, ?string $avatarUrl): ?string
    {
        if (! $avatarUrl || ! $this->isGoogleAvatarUrl($avatarUrl)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->accept('image/*')->get($avatarUrl);

            if (! $response->successful()) {
                return $avatarUrl;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $extension = match (true) {
                str_contains($contentType, 'image/png') => 'png',
                str_contains($contentType, 'image/webp') => 'webp',
                str_contains($contentType, 'image/gif') => 'gif',
                str_contains($contentType, 'image/jpeg') => 'jpg',
                default => null,
            };
            $contents = $response->body();

            if (! $extension || $contents === '' || strlen($contents) > 5 * 1024 * 1024) {
                return $avatarUrl;
            }

            $path = 'avatars/'.(string) $user->id.'/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $contents);

            return Storage::disk('public')->url($path);
        } catch (Throwable $exception) {
            Log::notice('No se pudo importar la foto de Google; se conserva su URL.', [
                'error' => $exception->getMessage(),
            ]);

            return $avatarUrl;
        }
    }

    /**
     * Foto guardada en un disco que ya no es el actual: p. ej. las que se subieron al disco local
     * del contenedor antes de que staging usara S3 (…/storage/avatars/…). Un contenedor nuevo ya
     * no las tiene y responden 404; así se reimporta la de Google en el siguiente inicio de sesión.
     */
    private function isStaleStoredAvatar(string $avatarUrl): bool
    {
        $path = (string) parse_url($avatarUrl, PHP_URL_PATH);

        return str_contains($path, '/avatars/')
            && ! str_starts_with($avatarUrl, rtrim(Storage::disk('public')->url(''), '/').'/');
    }

    private function isGoogleAvatarUrl(?string $avatarUrl): bool
    {
        $host = strtolower((string) parse_url($avatarUrl ?? '', PHP_URL_HOST));

        return $host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com');
    }
}
