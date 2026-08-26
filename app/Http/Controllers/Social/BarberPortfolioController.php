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

/**
 * Controlador de portafolio del barbero (rol barbero): gestión de sus
 * propios trabajos (fotos/videos) publicados en el feed social.
 */
class BarberPortfolioController extends Controller
{
    /**
     * Portafolio propio del barbero autenticado, con estadísticas de
     * interacción (reacciones, comentarios, guardados) de sus trabajos.
     */
    public function index(): View
    {
        $barber = auth()->user()->barberProfile;
        $works = Work::where('barbero_id', auth()->id())
            ->with(['images', 'reactions', 'comments', 'saves'])
            ->latest()
            ->paginate(12);

        $workIds = Work::where('barbero_id', auth()->id())->pluck('_id')->toArray();
        $stats = [
            'total_works' => count($workIds),
            'total_reactions' => Reaction::whereIn('work_id', $workIds)->count(),
            'total_comments' => Comment::whereIn('work_id', $workIds)->count(),
            'total_saves' => SavedWork::whereIn('work_id', $workIds)->count(),
        ];

        return view('barber.portfolio.index', compact('works', 'barber', 'stats'));
    }

    public function create(): View
    {
        return view('barber.portfolio.create');
    }

    /**
     * Publica un nuevo trabajo con una o varias imágenes/videos. Solo el
     * propio barbero puede publicar en su portafolio (nunca en el de otro).
     */
    public function store(Request $request, ?User $barber = null): RedirectResponse
    {
        $barberId = $barber?->id ?? auth()->id();
        abort_if((string) $barberId !== (string) auth()->id(), 403);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'media' => 'required|array|min:1|max:10',
            'media.*' => [
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
            'barbero_id' => $barberId,
            'title' => $request->title,
            'description' => $request->description,
            'work_date' => now(),
        ]);

        foreach ($request->file('media') as $file) {
            $mime = $file->getMimeType();
            $isVideo = str_starts_with($mime, 'video/');
            // Separar en carpetas por tipo para facilitar limpieza/CDN diferenciado.
            $folder = $isVideo ? 'portfolio/videos' : 'portfolio';
            $path = $file->store($folder, 'public');

            $work->images()->create([
                'image' => $path,
                'type' => $isVideo ? 'video' : 'image',
                'mime_type' => $mime,
            ]);
        }

        if ($barber) {
            return redirect()->route('barbers.public.show', $barber)->with('status', 'Trabajo publicado exitosamente.');
        }

        return redirect()->route('barber.portfolio.index')->with('status', 'Trabajo publicado exitosamente.');
    }

    /**
     * Elimina un trabajo del propio portafolio del barbero.
     */
    public function destroy(Work $work): RedirectResponse
    {
        abort_if((string) $work->barbero_id !== (string) auth()->id(), 403);
        $work->delete();

        return back()->with('status', 'Trabajo eliminado.');
    }
}
