<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Work;
use App\Models\Comment;
use App\Models\Reaction;

class BarberProfileController extends Controller
{
    public function index()
    {
        $barbers = User::role('barbero')->get();
        return view('barbers.index', compact('barbers'));
    }

    public function show(User $barber)
    {
        $works = Work::where('barbero_id', $barber->id)->latest()->paginate(12);
        return view('barbers.show', compact('barber', 'works'));
    }

    public function works(User $barber)
    {
        $works = Work::where('barbero_id', $barber->id)->latest()->paginate(12);
        return view('barbers.works', compact('barber', 'works'));
    }

    public function storeWork(Request $request, User $barber)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif|max:4096',
        ]);

        $work = Work::create([
            'barbero_id' => $barber->id,
            'title' => $request->title,
            'description' => $request->description,
            'work_date' => now(),
        ]);

        foreach ($request->file('images', []) as $image) {
            $path = $image->store('works', 'public');
            $work->images()->create(['image' => $path]);
        }

        return redirect()->route('barbers.show', $barber)->with('success', 'Trabajo subido correctamente.');
    }

    public function commentWork(Request $request, Work $work)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);
        $user = auth()->user();
        $work->comments()->create([
            'user_id' => $user->id,
            'comment' => $request->comment,
            'rating' => $request->rating,
        ]);
        return back()->with('success', 'Comentario agregado.');
    }

    public function reactWork(Request $request, Work $work)
    {
        $request->validate([
            'type' => 'required|string|max:20',
        ]);
        $user = auth()->user();
        // Solo un tipo de reacción por usuario por trabajo
        $work->reactions()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'type' => $request->type,
        ]);
        return back()->with('success', 'Reacción registrada.');
    }
}
