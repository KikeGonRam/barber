<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Models\SavedWork;
use App\Models\Work;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $works = Work::query()
            ->with(['barberUser:id,name', 'images:id,work_id,image', 'comments.user:id,name', 'reactions:id,work_id,user_id', 'saves:id,work_id,user_id'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $authUser = $request->user();

        return response()->json([
            'data' => $works->getCollection()->map(function (Work $work) use ($authUser) {
                return [
                    'id' => $work->id,
                    'title' => $work->title,
                    'description' => $work->description,
                    'work_date' => optional($work->work_date)?->toDateString(),
                    'barber' => [
                        'id' => $work->barberUser?->id,
                        'name' => $work->barberUser?->name,
                    ],
                    'images' => $work->images->map(fn ($img) => asset('storage/'.$img->image))->values(),
                    'reactions_count' => $work->reactions->count(),
                    'comments_count' => $work->comments->count(),
                    'saved_count' => $work->saves->count(),
                    'is_reacted' => $authUser ? $work->reactions->contains('user_id', $authUser->id) : false,
                    'is_saved' => $authUser ? $work->saves->contains('user_id', $authUser->id) : false,
                ];
            })->values(),
            'meta' => [
                'current_page' => $works->currentPage(),
                'last_page' => $works->lastPage(),
                'total' => $works->total(),
            ],
        ]);
    }

    public function react(Request $request, Work $work): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $reaction = Reaction::query()
            ->where('work_id', $work->id)
            ->where('user_id', $user->id)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $status = 'removed';
        } else {
            Reaction::query()->create([
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

    public function save(Request $request, Work $work): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $save = SavedWork::query()
            ->where('work_id', $work->id)
            ->where('user_id', $user->id)
            ->first();

        if ($save) {
            $save->delete();
            $status = 'removed';
        } else {
            SavedWork::query()->create([
                'work_id' => $work->id,
                'user_id' => $user->id,
            ]);
            $status = 'added';
        }

        return response()->json([
            'status' => $status,
        ]);
    }

    public function comment(Request $request, Work $work): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 401, 'No autorizado.');

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:500'],
        ]);

        $comment = $work->comments()->create([
            'user_id' => $user->id,
            'comment' => $data['comment'],
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'message' => 'Comentario publicado correctamente.',
            'data' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'user' => [
                    'id' => $comment->user?->id,
                    'name' => $comment->user?->name,
                ],
                'created_at' => optional($comment->created_at)?->toAtomString(),
            ],
        ], 201);
    }
}
