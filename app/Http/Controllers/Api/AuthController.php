<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MobileApiToken;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @group Autenticación
 *
 * Endpoints para el manejo de sesiones de usuario (Sanctum/Mobile API).
 */
class AuthController extends Controller
{
    /**
     * Iniciar Sesión
     *
     * Autentica al usuario y devuelve un token de acceso para la API móvil.
     *
     * @unauthenticated
     *
     * @bodyParam email string required El correo del usuario. Example: cliente@urbanblade.com
     * @bodyParam password string required La contraseña del usuario. Example: password123
     * @bodyParam device_name string Nombre del dispositivo. Example: iPhone 15 Pro
     *
     * @response {
     *  "message": "Autenticación exitosa.",
     *  "token_type": "Bearer",
     *  "token": "1|abc123def456...",
     *  "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com", "role": "cliente" }
     * }
     * @response 422 {
     *  "message": "Las credenciales no son válidas."
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Las credenciales no son válidas.',
            ], 422);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Debes verificar tu correo para iniciar sesión.',
            ], 403);
        }

        $issued = $user->issueMobileApiToken($credentials['device_name'] ?? 'Mobile App');

        return response()->json([
            'message' => 'Autenticación exitosa.',
            'token_type' => 'Bearer',
            'token' => $issued['token'],
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Registro de Usuario
     *
     * Crea una nueva cuenta de usuario y devuelve el token de acceso inicial.
     *
     * @unauthenticated
     *
     * @bodyParam name string required Nombre completo. Example: Nuevo Cliente
     * @bodyParam email string required Correo único. Example: nuevo@urbanblade.com
     * @bodyParam password string required Al menos 8 caracteres. Example: password123
     * @bodyParam password_confirmation string required Debe coincidir con password. Example: password123
     * @bodyParam device_name string Nombre del dispositivo. Example: Android Emulator
     *
     * @response 201 {
     *  "message": "Registro exitoso.",
     *  "token_type": "Bearer",
     *  "token": "2|xyz...",
     *  "user": { "id": 5, "name": "Nuevo Cliente", "role": "cliente" }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Count users before creating a new one
        $userCountBefore = User::count();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Mark email as verified for mobile app (can be changed later)
        $user->markEmailAsVerified();

        // Assign role based on user count
        if ($userCountBefore === 0) {
            // First user gets admin role
            $role = Role::firstOrCreate([
                'name' => 'administrador',
                'guard_name' => 'web',
            ]);
            $user->assignRole($role);
        } else {
            // All other users get client role
            $role = Role::firstOrCreate([
                'name' => 'cliente',
                'guard_name' => 'web',
            ]);
            $user->assignRole($role);

            // Create client profile
            Client::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ],
            ]);
        }

        event(new Registered($user));

        // Generate token for immediate login
        $issued = $user->issueMobileApiToken($validated['device_name'] ?? 'Mobile App');

        return response()->json([
            'message' => 'Cuenta creada exitosamente.',
            'token_type' => 'Bearer',
            'token' => $issued['token'],
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Obtener Token para Dashboard Web
     *
     * Genera un token API para usuarios autenticados en el dashboard web.
     * Permite que la interfaz web acceda a endpoints que requieren Bearer token.
     *
     * @authenticated
     *
     * @response {
     *  "ok": true,
     *  "message": "Token generado exitosamente.",
     *  "token_type": "Bearer",
     *  "token": "1|abc123def456...",
     *  "expires_at": "2026-11-10T12:00:00.000000Z"
     * }
     * @response 401 {
     *  "ok": false,
     *  "message": "No autenticado."
     * }
     */
    public function getWebApiToken(Request $request): JsonResponse
    {
        // Try to get user from web session first (dashboard context)
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'No autenticado. Por favor inicia sesión en el dashboard.',
            ], 401);
        }

        // Check if user has an existing valid token
        $existingToken = MobileApiToken::where('user_id', $user->id)
            ->where('name', 'Dashboard Web')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        // Use existing token or create a new one
        if ($existingToken) {
            // Return the token model but we can't get the plain token back
            // So we'll create a new one instead
            $existingToken->delete();
        }

        // Create new token that expires in 30 days
        $issued = $user->issueMobileApiToken(
            'Dashboard Web',
            ['*'],
            now()->addDays(30)
        );

        return response()->json([
            'ok' => true,
            'message' => 'Token generado exitosamente.',
            'token_type' => 'Bearer',
            'token' => $issued['token'],
            'expires_at' => $issued['token_model']->expires_at?->toISOString(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_token');

        if ($token instanceof MobileApiToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Renovar Token
     *
     * Revoca el token actual y emite uno nuevo con expiración actualizada.
     *
     * @response {
     *  "message": "Token renovado exitosamente.",
     *  "token_type": "Bearer",
     *  "token": "3|newtoken...",
     *  "expires_at": "2026-10-11T12:00:00.000000Z",
     *  "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com" }
     * }
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $currentToken = $request->attributes->get('mobile_token');

        if (! $currentToken instanceof MobileApiToken) {
            return response()->json([
                'message' => 'Token no válido.',
            ], 401);
        }

        // Guardar referencia al usuario antes de eliminar el token
        $user = $currentToken->user;

        // Revocar token actual
        $currentToken->delete();

        // Emitir nuevo token con expiración de 6 meses
        $issued = $user->issueMobileApiToken(
            $currentToken->name ?? 'Mobile App',
            $currentToken->abilities ?? ['*'],
            now()->addMonths(6)
        );

        return response()->json([
            'message' => 'Token renovado exitosamente.',
            'token_type' => 'Bearer',
            'token' => $issued['token'],
            'expires_at' => $issued['token']->expires_at?->toISOString(),
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Recuperar Contraseña (Solicitar Enlace)
     *
     * Envía un enlace de recuperación de contraseña al correo del usuario.
     *
     * @unauthenticated
     *
     * @bodyParam email string required El correo del usuario registrado. Example: usuario@urbanblade.com
     *
     * @response {
     *  "message": "Enlace de recuperación enviado a tu correo."
     * }
     * @response 400 {
     *  "message": "No se pudo enviar el enlace de recuperación."
     * }
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $validated['email']]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Enlace de recuperación enviado a tu correo.',
            ]);
        }

        return response()->json([
            'message' => 'No se pudo enviar el enlace de recuperación.',
        ], 400);
    }

    /**
     * Restablecer Contraseña
     *
     * Restablece la contraseña del usuario usando el token recibido por correo.
     *
     * @unauthenticated
     *
     * @bodyParam token string required El token de recuperación recibido por correo. Example: abc123
     * @bodyParam email string required El correo del usuario. Example: usuario@urbanblade.com
     * @bodyParam password string required Nueva contraseña (mínimo 8 caracteres). Example: nuevaPassword123
     * @bodyParam password_confirmation string required Confirmación de la nueva contraseña. Example: nuevaPassword123
     *
     * @response {
     *  "message": "Contraseña restablecida exitosamente."
     * }
     * @response 400 {
     *  "message": "Token de recuperación inválido."
     * }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Contraseña restablecida exitosamente.',
            ]);
        }

        return response()->json([
            'message' => 'Token de recuperación inválido o expirado.',
        ], 400);
    }

    private function userPayload(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->values(),
            'client_id' => $user->clientProfile?->id,
            'barber_id' => $user->barberProfile?->id,
        ];
    }
}

