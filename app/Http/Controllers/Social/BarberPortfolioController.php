<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BarberPortfolioController extends Controller
{
    public function index(): View
    {
        $barber = auth()->user()->barberProfile;
        $works = Work::where('barbero_id', auth()->id())->latest()->paginate(12);
        
        return view('barber.portfolio.index', compact('works'));
    }

    public function create(): View
    {
        return view('barber.portfolio.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $work = Work::create([
            'barbero_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'work_date' => now(),
        ]);

        foreach ($request->file('images') as $image) {
            $path = $image->store('portfolio', 'public');
            $work->images()->create(['image' => $path]);
        }

        return redirect()->route('barber.portfolio.index')->with('status', 'Trabajo publicado exitosamente en tu portafolio.');
    }

    public function destroy(Work $work): RedirectResponse
    {
        abort_if($work->barbero_id !== auth()->id(), 403);
        
        $work->delete(); // This should delete images if using boot or cascade
        
        return back()->with('status', 'Trabajo eliminado de tu portafolio.');
    }
}
