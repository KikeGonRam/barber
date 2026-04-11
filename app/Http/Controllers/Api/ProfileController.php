<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @group Gestión de Perfil
 *
 * Endpoints para gestionar el perfil del usuario autenticado.
 */
class ProfileController extends Controller
{
    /**
     * Obtener Perfil
     *
     * Devuelve la información del perfil del usuario autenticado.
     *
     * @response {
     *  "user": {
     *    "id": 1,
     *    "name": "Juan Pérez",
     *    "email": "juan@example.com",
     *    "email_verified_at": "2026-04-01T10:00:00.000000Z",
     *    "created_at": "2026-04-01T10:00:00.000000Z",
     *    "roles": ["cliente"],
     *    "client_id": 1,
     *    "barber_id": null
     *  }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'roles' => $user->roles->pluck('name')->values(),
                'client_id' => $user->clientProfile?->id,
                'barber_id' => $user->barberProfile?->id,
            ],
        ]);
    }

    /**
     * Actualizar Perfil
     *
     * Actualiza la información del perfil del usuario autenticado.
     *
     * @bodyParam name string Nombre completo. Example: Juan Pérez Actualizado
     * @bodyParam email string Correo electrónico único. Example: nuevo@example.com
     *
     * @response {
     *  "message": "Perfil actualizado exitosamente",
     *  "user": { "id": 1, "name": "Juan Pérez Actualizado", "email": "nuevo@example.com" }
     * }
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Actualizar Contraseña
     *
     * Cambia la contraseña del usuario autenticado.
     *
     * @bodyParam current_password string required Contraseña actual. Example: password123
     * @bodyParam password string required Nueva contraseña (mínimo 8 caracteres). Example: nuevaPassword456
     * @bodyParam password_confirmation string required Confirmación de la nueva contraseña. Example: nuevaPassword456
     *
     * @response {
     *  "message": "Contraseña actualizada exitosamente"
     * }
     * @response 422 {
     *  "message": "La contraseña actual es incorrecta."
     * }
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.',
            ], 422);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente',
        ]);
    }

    /**
     * Eliminar Cuenta
     *
     * Elimina permanentemente la cuenta del usuario autenticado.
     *
     * @bodyParam password string required Contraseña actual para confirmar eliminación. Example: password123
     *
     * @response {
     *  "message": "Cuenta eliminada exitosamente"
     * }
     * @response 422 {
     *  "message": "Contraseña incorrecta."
     * }
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required'],
        ]);

        if (!Hash::check($validated['password'], $request->user()->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta.',
            ], 422);
        }

        $user = $request->user();

        // Eliminar todos los tokens API
        $user->mobileApiTokens()->delete();

        // Eliminación suave del usuario (soft delete)
        $user->delete();

        return response()->json([
            'message' => 'Cuenta eliminada exitosamente',
        ]);
    }
}
