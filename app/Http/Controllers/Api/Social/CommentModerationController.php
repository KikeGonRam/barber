<?php

namespace App\Http\Controllers\Api\Social;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\PublicName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Herramientas del usuario para el contenido del muro: reportar un comentario y bloquear a su
 * autor. Exigidas por la política de contenido de usuarios de Google Play.
 *
 * El id del autor no se expone en el muro (solo «Luis G.»): por eso el bloqueo se hace a través
 * del comentario y no con el id del usuario.
 */
class CommentModerationController extends Controller
{
    /**
     * Reporta un comentario ajeno. Idempotente: reportar dos veces lo mismo no duplica el reporte.
     */
    public function report(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(CommentReport::REASONS)],
            'details' => ['nullable', 'string', 'max:200'],
        ]);

        abort_if((string) $comment->getAttribute('user_id') === (string) $user->id, 422, 'No puedes reportar tu propio comentario.');

        $existing = CommentReport::query()
            ->where('comment_id', (string) $comment->getKey())
            ->where('reporter_id', (string) $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Ya habías reportado este comentario. Lo estamos revisando.']);
        }

        CommentReport::create([
            'comment_id' => (string) $comment->getKey(),
            'work_id' => (string) $comment->getAttribute('work_id'),
            'reporter_id' => (string) $user->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => CommentReport::STATUS_PENDING,
        ]);

        return response()->json(['message' => 'Gracias. Revisaremos el comentario y, si incumple las reglas, lo retiraremos.'], 201);
    }

    /**
     * Bloquea al autor de un comentario: dejas de ver sus comentarios. Idempotente.
     */
    public function blockAuthor(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $authorId = (string) $comment->getAttribute('user_id');
        abort_if($authorId === (string) $user->id, 422, 'No puedes bloquearte a ti mismo.');
        abort_if($authorId === '', 404, 'El comentario ya no tiene autor.');

        $block = UserBlock::query()
            ->where('blocker_id', (string) $user->id)
            ->where('blocked_id', $authorId)
            ->first();

        if ($block) {
            return response()->json(['message' => 'Ya habías bloqueado a este usuario.']);
        }

        UserBlock::create(['blocker_id' => (string) $user->id, 'blocked_id' => $authorId]);

        return response()->json(['message' => 'Usuario bloqueado. Ya no verás sus comentarios.'], 201);
    }

    /**
     * Usuarios que bloqueaste (con el nombre público, nunca el completo) para poder deshacerlo.
     */
    public function blocks(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $blocks = UserBlock::query()->where('blocker_id', (string) $user->id)->latest('id')->get();
        $names = User::query()->whereIn('_id', $blocks->pluck('blocked_id')->all())->get()
            ->mapWithKeys(fn (User $u) => [(string) $u->id => $u->name]);

        return response()->json([
            'data' => $blocks->map(fn (UserBlock $b) => [
                'id' => (string) $b->id,
                'name' => PublicName::of($names->get((string) $b->blocked_id)),
            ])->values(),
        ]);
    }

    /**
     * Deshace un bloqueo propio. Solo puedes borrar los tuyos.
     */
    public function unblock(Request $request, string $block): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $row = UserBlock::query()->where('_id', $block)->where('blocker_id', (string) $user->id)->first();
        abort_if(! $row, 404, 'Bloqueo no encontrado.');

        $row->delete();

        return response()->json(['message' => 'Usuario desbloqueado.']);
    }
}
