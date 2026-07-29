<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\SavedWork;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarberPortfolioController extends Controller
{
    public function index(): View
    {
        $barber = auth()->user()->barberProfile;
        $works  = Work::where('barbero_id', auth()->id())
            ->with(['images', 'reactions', 'comments', 'saves'])
            ->latest()
            ->paginate(12);

        $workIds = Work::where('barbero_id', auth()->id())->pluck('_id')->toArray();
        $stats = [
            'total_works'    => count($workIds),
            'total_reactions'=> Reaction::whereIn('work_id', $workIds)->count(),
            'total_comments' => Comment::whereIn('work_id', $workIds)->count(),
            'total_saves'    => SavedWork::whereIn('work_id', $workIds)->count(),
        ];

        return view('barber.portfolio.index', compact('works', 'barber', 'stats'));
    }

    public function create(): View
    {
        return view('barber.portfolio.create');
    }

    public function store(Request $request, ?User $barber = null): RedirectResponse
    {
        $barberId = $barber?->id ?? auth()->id();
        abort_if($barberId !== auth()->id(), 403);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'media'       => 'required|array|min:1|max:10',
            'media.*'     => [
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    $mime = $value->getMimeType();
                    $allowed = [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
                        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
                        'video/mpeg', 'video/ogg',
                    ];
                    if (! in_array($mime, $allowed, true)) {
                        $fail('Solo se permiten imágenes (JPG, PNG, WEBP) y videos (MP4, WEBM, MOV).');
                    }
                },
            ],
        ]);

        $work = Work::create([
            'barbero_id'  => $barberId,
            'title'       => $request->title,
            'description' => $request->description,
            'work_date'   => now(),
        ]);

        foreach ($request->file('media') as $file) {
            $mime     = $file->getMimeType();
            $isVideo  = str_starts_with($mime, 'video/');
            $folder   = $isVideo ? 'portfolio/videos' : 'portfolio';
            $path     = $file->store($folder, 'public');

            $work->images()->create([
                'image'     => $path,
                'type'      => $isVideo ? 'video' : 'image',
                'mime_type' => $mime,
            ]);
        }

        if ($barber) {
            return redirect()->route('barbers.public.show', $barber)->with('status', 'Trabajo publicado exitosamente.');
        }

        return redirect()->route('barber.portfolio.index')->with('status', 'Trabajo publicado exitosamente.');
    }

    public function destroy(Work $work): RedirectResponse
    {
        abort_if($work->barbero_id !== auth()->id(), 403);
        $work->delete();

        return back()->with('status', 'Trabajo eliminado.');
    }
}
