<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Models\SavedWork;
use App\Models\Work;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del feed social (cualquier usuario autenticado): ver el
 * feed de trabajos de los barberos, reaccionar, guardar y comentar.
 */
class SocialController extends Controller
{
    /**
     * Feed principal con los trabajos más recientes de todos los barberos.
     */
    public function feed()
    {
        $works = Work::with(['barberUser.barberProfile', 'images', 'comments.user', 'reactions', 'saves'])
            ->latest()
            ->paginate(10);

        return view('social.feed', compact('works'));
    }

    /**
     * Alterna el "like" del usuario sobre un trabajo (toggle: si ya existe
     * la reacción la quita, si no existe la crea).
     */
    public function react(Request $request, Work $work): JsonResponse
    {
        $user = auth()->user();

        $reaction = Reaction::where('work_id', $work->id)
            ->where('user_id', $user->id)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $status = 'removed';
        } else {
            Reaction::create([
                'work_id' => $work->id,
                'user_id' => $user->id,
                'type' => 'like',
            ]);
            $status = 'added';
        }

        return response()->json([
            'status' => $status,
            'count' => $work->reactions()->count(),
        ]);
    }

    /**
     * Alterna el guardado de un trabajo para el usuario (toggle igual que react()).
     */
    public function save(Request $request, Work $work): JsonResponse
    {
        $user = auth()->user();
        $workId = (string) $work->id;
        $userId = (string) $user->id;

        $save = SavedWork::where('work_id', $workId)
            ->where('user_id', $userId)
            ->first();

        if ($save) {
            $save->delete();
            $status = 'removed';
        } else {
            SavedWork::create([
                'work_id' => $workId,
                'user_id' => $userId,
            ]);
            $status = 'added';
        }

        return response()->json([
            'status' => $status,
        ]);
    }

    /**
     * Publica un comentario en un trabajo. Responde JSON si la petición lo
     * pide (AJAX) o redirige de vuelta si es un envío de formulario normal.
     */
    public function comment(Request $request, Work $work)
    {
        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        $comment = $work->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['comment' => $comment]);
        }

        return redirect()->back()->with('success', 'Comentario publicado correctamente.');
    }
}
