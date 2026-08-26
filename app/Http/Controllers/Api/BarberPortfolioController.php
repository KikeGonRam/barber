<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Work;
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

        return response()->json([
            'works' => $works->map(function ($work) {
                return [
                    'id' => $work->id,
                    'title' => $work->title,
                    'description' => $work->description,
                    'images' => $work->images->map(fn ($img) => asset('storage/'.$img->image))->values(),
                    'reactions_count' => $work->reactions->count(),
                    'comments_count' => $work->comments->count(),
                    'created_at' => $work->created_at,
                ];
            })->values(),
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
            'description' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:2048'],
        ]);

        $work = Work::create([
            'barbero_id' => $barber->user_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'work_date' => now(),
        ]);

        // Subir imágenes si existen
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('portfolio', 'public');

                $work->images()->create(['image' => $path]);
            }
        }

        $work->load('images');

        return response()->json([
            'message' => 'Trabajo creado exitosamente',
            'work' => [
                'id' => $work->id,
                'title' => $work->title,
                'description' => $work->description,
                'images' => $work->images->map(fn ($img) => asset('storage/'.$img->image))->values(),
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
