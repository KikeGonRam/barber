<?php

namespace App\Http\Controllers\Api;

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
}
