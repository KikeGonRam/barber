<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
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
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'roles' => $user->roleNames()->values(),
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
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
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

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
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
    /**
     * Perfil completo del Barbero autenticado
     *
     * Devuelve bio, rating y estadísticas de actividad del barbero en una sola llamada.
     */
    public function showBarberProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (! $barber) {
            return response()->json(['message' => 'Perfil de barbero no encontrado'], 404);
        }

        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $citasHoy = Appointment::where('barber_id', (string) $barber->id)
            ->where('fecha', $today)->count();
        $completadasMes = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')
            ->where('fecha', '>=', $startOfMonth)->count();
        $totalCompletadas = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')->count();

        return response()->json([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'especialidades' => $barber->especialidades ?? '',
            'descripcion' => $barber->descripcion ?? '',
            'calificacion_promedio' => round((float) ($barber->calificacion_promedio ?? 0), 1),
            'total_resenas' => (int) ($barber->total_resenas ?? 0),
            'activo' => (bool) ($barber->activo ?? true),
            'stats' => [
                'citas_hoy' => $citasHoy,
                'completadas_mes' => $completadasMes,
                'total_completadas' => $totalCompletadas,
            ],
        ]);
    }

    /**
     * Devuelve solo especialidades y descripción del barbero autenticado (versión ligera de showBarberProfile).
     */
    public function showBarberBio(Request $request): JsonResponse
    {
        $barber = $request->user()->barberProfile;

        if (! $barber) {
            return response()->json(['message' => 'Perfil de barbero no encontrado'], 404);
        }

        return response()->json([
            'especialidades' => $barber->especialidades ?? '',
            'descripcion' => $barber->descripcion ?? '',
        ]);
    }

    /**
     * Actualiza especialidades y descripción del perfil del barbero autenticado.
     */
    public function updateBarberBio(Request $request): JsonResponse
    {
        $barber = $request->user()->barberProfile;

        if (! $barber) {
            return response()->json(['message' => 'Perfil de barbero no encontrado'], 404);
        }

        $validated = $request->validate([
            'especialidades' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $barber->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado',
            'especialidades' => $barber->especialidades,
            'descripcion' => $barber->descripcion,
        ]);
    }

    /**
     * Guarda/actualiza el token de push de Expo del usuario autenticado (app móvil)
     * para poder enviarle notificaciones push.
     */
    public function savePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'expo_push_token' => $validated['token'],
        ]);

        return response()->json(['message' => 'Token registrado']);
    }

    /**
     * Elimina (soft delete) la cuenta del usuario autenticado tras confirmar su contraseña
     * y revoca todos sus tokens de la API móvil.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
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
