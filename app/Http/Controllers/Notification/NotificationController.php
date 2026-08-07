<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador de notificaciones para cualquier usuario autenticado:
 * bandeja de notificaciones, marcado de leídas, preferencias y polling AJAX.
 */
class NotificationController extends Controller
{
    /**
     * Bandeja de notificaciones del usuario autenticado (paginada).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Marca todas las notificaciones no leídas del usuario como leídas.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificaciones marcadas como leídas.');
    }

    /**
     * Marca una notificación puntual como leída, identificada por su _id de Mongo.
     */
    public function markOneRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('_id', $id)
            ->first();

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
        }

        return back()->with('status', 'Notificación marcada como leída.');
    }

    /**
     * Muestra el formulario de preferencias de notificación (canal in-app/email/sms/whatsapp).
     */
    public function preferences(Request $request)
    {
        return view('notifications.preferences', [
            'prefs' => $request->user()->notificationPreferences(),
        ]);
    }

    /**
     * Guarda las preferencias de notificación del usuario.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();

        $prefs = [
            'in_app' => $request->boolean('in_app'),
            'email' => $request->boolean('email'),
            'sms' => $request->boolean('sms'),
            'whatsapp' => $request->boolean('whatsapp'),
            'promociones' => $request->boolean('promociones'),
        ];

        $user->update(['notification_preferences' => $prefs]);

        // Mantener sincronizado el perfil de cliente (fuente legada) si existe.
        if ($user->clientProfile) {
            $user->clientProfile->update(['preferencias_notificacion' => $prefs]);
        }

        return back()->with('status', 'Preferencias de notificación actualizadas.');
    }

    /**
     * Endpoint JSON para el polling periódico del frontend: conteo de no
     * leídas y las 5 más recientes, sin recargar la página.
     */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id' => (string) $n->id,
                'type' => $n->type,
                'data' => $n->data,
                // optional() evita error si created_at viniera nulo en algún registro legado.
                'created_at' => optional($n->created_at)->toAtomString(),
            ]);

        return response()->json([
            'unread' => $unread,
            'notifications' => $notifications,
        ]);
    }
}
