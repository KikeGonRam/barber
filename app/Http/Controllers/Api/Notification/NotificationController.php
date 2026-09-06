<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de notificaciones del usuario autenticado (cualquier rol).
 * Usa el sistema de notificaciones nativo de Laravel (tabla notifications de MongoDB).
 */
class NotificationController extends Controller
{
    /**
     * Lista paginada de notificaciones del usuario autenticado, con conteo de no leídas.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(20)->withQueryString();

        return response()->json([
            'data' => $notifications->getCollection()->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => optional($notification->read_at)?->toAtomString(),
                    'created_at' => optional($notification->created_at)?->toAtomString(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Marca como leídas todas las notificaciones pendientes del usuario autenticado.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Notificaciones marcadas como leidas.',
            'unread' => 0,
        ]);
    }

    /**
     * Marca una notificación puntual como leída, validando que pertenezca al usuario autenticado.
     */
    public function markOneRead(Request $request, string $id): JsonResponse
    {
        // _id es el identificador nativo de MongoDB (las notificaciones no usan uuid propio)
        $notification = $request->user()
            ->notifications()
            ->where('_id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notificación no encontrada.'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Elimina una notificación puntual del usuario autenticado.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('_id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notificación no encontrada.'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notificación eliminada.',
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Preferencias de canal de notificación del usuario autenticado
     * (in_app/email/sms/whatsapp/push/promociones).
     */
    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->notificationPreferences(),
        ]);
    }

    /**
     * Actualiza las preferencias de notificación del usuario autenticado.
     * Igual que la versión web (Notification\NotificationController), hace
     * merge sobre las preferencias actuales en vez de reemplazarlas por
     * completo, para que un cliente que solo manda un subconjunto de
     * canales (p. ej. solo "push") no borre los demás.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'in_app' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            'whatsapp' => ['sometimes', 'boolean'],
            'push' => ['sometimes', 'boolean'],
            'promociones' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $prefs = array_merge($user->notificationPreferences(), $validated);

        $user->update(['notification_preferences' => $prefs]);

        if ($user->clientProfile) {
            $user->clientProfile->update(['preferencias_notificacion' => $prefs]);
        }

        return response()->json([
            'message' => 'Preferencias de notificación actualizadas.',
            'data' => $prefs,
        ]);
    }
}
