<?php

namespace App\Http\Controllers\Api\Barber;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\SavedWork;
use App\Models\Work;
use App\Models\WorkImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Portafolio de Barbero
 *
 * Endpoints para gestionar el portafolio de trabajos del barbero.
 */
class BarberPortfolioController extends Controller
{
    /**
     * Listar Trabajos del Barbero
     *
     * Devuelve todos los trabajos del barbero autenticado.
     *
     * @response {
     *  "works": [
     *    {
     *      "id": 1,
     *      "title": "Corte Clásico",
     *      "description": "Corte de cabello clásico con degradado",
     *      "images": ["https://example.com/storage/works/image1.jpg"],
     *      "created_at": "2026-04-11T10:00:00.000000Z"
     *    }
     *  ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (! $barber) {
            return response()->json([
                'message' => 'No tienes perfil de barbero.',
            ], 403);
        }

        $works = Work::where('barbero_id', $barber->user_id)
            ->with(['images', 'reactions', 'comments'])
            ->latest()
            ->get();

        $workIds = $works->pluck('_id')->map(fn ($id) => (string) $id)->all();

        return response()->json([
            'works' => $works->map(function (Work $work) {
                return [
                    'id' => $work->id,
                    'title' => $work->title,
                    'description' => $work->description,
                    'media' => $work->images->map(fn (WorkImage $item) => [
                        'id' => (string) $item->id,
                        'url' => asset('storage/'.$item->image),
                        'type' => $item->isVideo() ? 'video' : 'image',
                        'mime_type' => $item->mime_type,
                    ])->values(),
                    'images' => $work->images->map(fn ($img) => asset('storage/'.$img->image))->values(),
                    'reactions_count' => $work->reactions->count(),
                    'comments_count' => $work->comments->count(),
                    'created_at' => $work->created_at,
                ];
            })->values(),
            'stats' => [
                'total_works' => count($workIds),
                'total_reactions' => empty($workIds) ? 0 : Reaction::whereIn('work_id', $workIds)->count(),
                'total_comments' => empty($workIds) ? 0 : Comment::whereIn('work_id', $workIds)->count(),
                'total_saves' => empty($workIds) ? 0 : SavedWork::whereIn('work_id', $workIds)->count(),
            ],
        ]);
    }

    /**
     * Crear Trabajo
     *
     * Crea un nuevo trabajo en el portafolio del barbero con imágenes opcionales.
     *
     * @bodyParam title string required Título del trabajo. Example: Corte Moderno con Degradado
     * @bodyParam description string Descripción del trabajo. Example: Corte moderno con degradado lateral y textura superior
     * @bodyParam images array Array de imágenes (archivos).
     *
     * @response 201 {
     *  "message": "Trabajo creado exitosamente",
     *  "work": { "id": 1, "title": "Corte Moderno con Degradado" }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (! $barber) {
            return response()->json([
                'message' => 'No tienes perfil de barbero.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/x-msvideo,video/mpeg,video/ogg', 'max:51200'],
            // Compatibilidad con el contrato anterior de la API movil.
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:2048'],
        ]);

        $work = Work::create([
            'barbero_id' => $barber->user_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'work_date' => now(),
        ]);

        // Subir imágenes si existen
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $mime = $file->getMimeType();
                $isVideo = str_starts_with($mime, 'video/');
                $path = $file->store($isVideo ? 'portfolio/videos' : 'portfolio', 'public');

                $work->images()->create([
                    'image' => $path,
                    'type' => $isVideo ? 'video' : 'image',
                    'mime_type' => $mime,
                ]);
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('portfolio', 'public');
                $work->images()->create([
                    'image' => $path,
                    'type' => 'image',
                    'mime_type' => $image->getMimeType(),
                ]);
            }
        }

        $work->load('images');

        return response()->json([
            'message' => 'Trabajo creado exitosamente',
            'work' => [
                'id' => $work->id,
                'title' => $work->title,
                'description' => $work->description,
                'media' => $work->images->map(fn (WorkImage $item) => [
                    'id' => (string) $item->id,
                    'url' => asset('storage/'.$item->image),
                    'type' => $item->isVideo() ? 'video' : 'image',
                    'mime_type' => $item->mime_type,
                ])->values(),
            ],
        ], 201);
    }

    /**
     * Eliminar Trabajo
     *
     * Elimina un trabajo del portafolio del barbero.
     *
     * @response {
     *  "message": "Trabajo eliminado correctamente"
     * }
     */
    public function destroy(Request $request, Work $work): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (! $barber) {
            return response()->json([
                'message' => 'No tienes perfil de barbero.',
            ], 403);
        }

        // Verificar que el trabajo pertenezca al barbero
        if ((string) $work->barbero_id !== (string) $barber->user_id) {
            return response()->json([
                'message' => 'No autorizado para eliminar este trabajo.',
            ], 403);
        }

        // Eliminar imágenes del almacenamiento
        foreach ($work->images as $image) {
            if ($image->image) {
                Storage::disk('public')->delete($image->image);
            }
        }

        $work->delete();

        return response()->json([
            'message' => 'Trabajo eliminado correctamente',
        ]);
    }
}
