<?php

namespace App\Http\Controllers\Api\Admin\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Moderación del muro por el administrador: ve los comentarios reportados y decide qué hacer.
 * Es la parte «moderación» que exige la política de contenido de usuarios de Google Play.
 */
class CommentReportAdminController extends Controller
{
    public const ACTION_REMOVE = 'remove';

    public const ACTION_REMOVE_AND_BAN = 'remove_and_ban';

    public const ACTION_DISMISS = 'dismiss';

    /**
     * Comentarios con reportes pendientes, uno por comentario, con cuántos reportes y por qué motivos.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = CommentReport::query()
            ->where('status', CommentReport::STATUS_PENDING)
            ->oldest('id')
            ->limit(500)
            ->get()
            ->groupBy('comment_id');

        $comments = Comment::query()->whereIn('_id', $reports->keys()->all())->get()->keyBy(fn (Comment $c) => (string) $c->id);
        $authors = User::query()->whereIn('_id', $comments->pluck('user_id')->filter()->unique()->values()->all())->get()
            ->keyBy(fn (User $u) => (string) $u->id);
        $works = Work::query()->whereIn('_id', $comments->pluck('work_id')->filter()->unique()->values()->all())->get()
            ->keyBy(fn (Work $w) => (string) $w->id);

        $data = $reports->map(function ($group, $commentId) use ($comments, $authors, $works) {
            /** @var Comment|null $comment */
            $comment = $comments->get((string) $commentId);
            if (! $comment) {
                return null;
            }

            $author = $authors->get((string) $comment->user_id);
            $work = $works->get((string) $comment->work_id);

            return [
                'comment_id' => (string) $commentId,
                'comment' => $comment->comment,
                'author' => [
                    'id' => $author ? (string) $author->id : null,
                    'name' => $author?->name,
                    'comments_banned' => (bool) $author?->getAttribute('comments_banned_at'),
                ],
                'work' => ['id' => $work ? (string) $work->id : null, 'title' => $work?->title],
                'reports_count' => $group->count(),
                'reasons' => $group->countBy('reason')->all(),
                'first_reported_at' => optional($group->first()?->created_at)?->toAtomString(),
            ];
        })->filter()->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Resuelve todos los reportes pendientes de un comentario.
     *
     * - remove: elimina el comentario.
     * - remove_and_ban: lo elimina y el autor ya no puede comentar.
     * - dismiss: el comentario cumple las reglas; se descartan los reportes y se conserva.
     */
    public function resolve(Request $request, string $comment): JsonResponse
    {
        $admin = $request->user();

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in([self::ACTION_REMOVE, self::ACTION_REMOVE_AND_BAN, self::ACTION_DISMISS])],
        ]);

        $pending = CommentReport::query()
            ->where('comment_id', $comment)
            ->where('status', CommentReport::STATUS_PENDING)
            ->get();
        abort_if($pending->isEmpty(), 404, 'No hay reportes pendientes para este comentario.');

        $model = Comment::query()->where('_id', $comment)->first();
        $status = $data['action'] === self::ACTION_DISMISS ? CommentReport::STATUS_DISMISSED : CommentReport::STATUS_REMOVED;

        if ($model && $data['action'] === self::ACTION_REMOVE_AND_BAN && $model->user_id) {
            $author = User::query()->where('_id', (string) $model->user_id)->first();
            $author?->forceFill(['comments_banned_at' => now()])->save();
        }
        if ($model && $status === CommentReport::STATUS_REMOVED) {
            $model->delete();
        }

        foreach ($pending as $report) {
            $report->update([
                'status' => $status,
                'resolved_by' => (string) $admin?->id,
                'resolved_at' => now(),
            ]);
        }

        return response()->json([
            'message' => match ($data['action']) {
                self::ACTION_REMOVE => 'Comentario eliminado.',
                self::ACTION_REMOVE_AND_BAN => 'Comentario eliminado y autor vetado para comentar.',
                default => 'Reportes descartados; el comentario se conserva.',
            },
            'resolved_reports' => $pending->count(),
        ]);
    }
}
