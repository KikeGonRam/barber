<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
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