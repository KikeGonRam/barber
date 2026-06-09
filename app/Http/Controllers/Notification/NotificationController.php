<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificaciones marcadas como leídas.');
    }

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

    public function poll(Request $request): JsonResponse
    {
        $user  = $request->user();
        $unread = $user->unreadNotifications()->count();

        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id'         => (string) $n->id,
                'type'       => $n->type,
                'data'       => $n->data,
                'created_at' => optional($n->created_at)->toAtomString(),
            ]);

        return response()->json([
            'unread'        => $unread,
            'notifications' => $notifications,
        ]);
    }
}
