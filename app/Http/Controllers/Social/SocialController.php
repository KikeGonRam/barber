<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Work;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\SavedWork;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SocialController extends Controller
{
    public function feed()
    {
        $works = Work::with(['barberUser', 'images', 'comments.user', 'reactions', 'saves'])
            ->latest()
            ->paginate(10);
            
        return view('social.feed', compact('works'));
    }

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
                'type' => 'like'
            ]);
            $status = 'added';
        }

        return response()->json([
            'status' => $status,
            'count' => $work->reactions()->count()
        ]);
    }

    public function save(Request $request, Work $work): JsonResponse
    {
        $user = auth()->user();
        
        $save = SavedWork::where('work_id', $work->id)
            ->where('user_id', $user->id)
            ->first();

        if ($save) {
            $save->delete();
            $status = 'removed';
        } else {
            SavedWork::create([
                'work_id' => $work->id,
                'user_id' => $user->id,
            ]);
            $status = 'added';
        }

        return response()->json([
            'status' => $status
        ]);
    }

    public function comment(Request $request, Work $work)
    {
        $request->validate([
            'comment' => 'required|string|max:500'
        ]);

        $comment = $work->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment
        ]);

        return redirect()->back()->with('success', 'Comentario publicado correctamente.');
    }
}
