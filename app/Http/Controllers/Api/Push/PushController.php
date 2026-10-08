<?php

namespace App\Http\Controllers\Api\Push;

use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de suscripciones Web Push del usuario autenticado: registrar una
 * nueva suscripción del navegador, darla de baja, y servir la clave pública
 * VAPID que el navegador necesita para PushManager.subscribe().
 */
class PushController extends Controller
{
    /**
     * Clave pública VAPID (no es un secreto — viaja al navegador por
     * diseño). Pública para que la pantalla de login/onboarding pueda
     * pedirla incluso antes de que el usuario tenga sesión, si hiciera falta.
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('services.vapid.public_key'),
        ]);
    }

    /**
     * Registra (o actualiza) la suscripción Web Push del dispositivo/navegador
     * actual. Identificada por 'endpoint' porque el cliente nunca conoce el
     * _id de Mongo del registro — un mismo endpoint solo debe existir una vez.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $request->user();

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => (string) $user->id,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
            ],
        );

        // Suscribir el navegador es aceptar avisos: el canal push viene apagado por defecto y, sin
        // esto, el servidor descartaba todo aviso aunque la suscripción existiera. Igual que con el
        // token de Android, solo se enciende si la persona nunca eligió; si lo apagó, se respeta.
        if (! ProfileController::pushChoiceMade($user)) {
            $own = $user->getAttribute('notification_preferences');
            $user->update(['notification_preferences' => array_merge(is_array($own) ? $own : [], ['push' => true])]);
        }

        return response()->json(['message' => 'Suscripción registrada.'], 201);
    }

    /**
     * Da de baja la suscripción del dispositivo/navegador actual (p. ej. el
     * usuario desactivó el permiso, o cerró sesión y quiere dejar de recibir
     * avisos en ese navegador).
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::where('user_id', (string) $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['message' => 'Suscripción eliminada.']);
    }
}
