<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Service;
use App\Models\Work;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del catálogo público (clientes/visitantes).
 * Expone servicios activos, listado de barberos con rating agregado,
 * perfil público de barbero con portafolio/reseñas, y creación de reseñas.
 */
class CatalogController extends Controller
{
    // Lista servicios activos ordenados alfabéticamente
    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria', 'precio', 'duracion_min', 'imagen', 'descripcion']);

        return response()->json([
            'data' => $services,
        ]);
    }

    // Lista barberos activos con rating promedio y total de reseñas precalculados en lote
    public function barbers(): JsonResponse
    {
        $barbers = Barber::query()
            ->with('user:id,name')
            ->where('activo', true)
            ->orderBy('id')
            ->get();

        // Aggregate review stats in one query grouped in PHP (avoids N+1)
        $barberIds = $barbers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $reviewStats = BarberReview::whereIn('barber_id', $barberIds)
            ->get(['barber_id', 'rating'])
            ->groupBy('barber_id')
            ->map(fn ($group) => [
                'avg' => round($group->avg('rating'), 1),
                'count' => $group->count(),
            ]);

        $payload = $barbers->map(fn (Barber $barber) => [
            'id' => $barber->id,
            'slug' => $barber->slug,
            'user' => $barber->user ? ['id' => $barber->user->id, 'name' => $barber->user->name] : null,
            'especialidades' => $barber->especialidades ?? '',
            'descripcion' => $barber->descripcion ?? '',
            'foto' => $barber->foto ? asset('storage/'.$barber->foto) : null,
            'activo' => (bool) ($barber->activo ?? true),
            'avg_rating' => $reviewStats[(string) $barber->id]['avg'] ?? null,
            'total_reviews' => $reviewStats[(string) $barber->id]['count'] ?? 0,
        ])->values();

        return response()->json(['data' => $payload]);
    }

    // Perfil público de un barbero: portafolio, reseñas y si el cliente actual puede reseñarlo
    public function showBarber(Barber $barber, Request $request): JsonResponse
    {
        $barber->load('user:id,name,email,created_at');

        $works = Work::where('barbero_id', (string) $barber->user_id)
            ->with(['images'])
            ->latest()
            ->limit(12)
            ->get();

        $citasCompletadas = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')
            ->count();

        $reviews = BarberReview::where('barber_id', (string) $barber->id)
            ->with('client.user:id,name')
            ->latest()
            ->get();

        $avgRating = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;

        $client = $request->user()?->clientProfile;
        $alreadyReviewed = false;
        $canReview = false;

        if ($client) {
            $alreadyReviewed = BarberReview::where('barber_id', (string) $barber->id)
                ->where('client_id', (string) $client->id)
                ->exists();

            // Solo puede reseñar si aún no lo hizo y tuvo al menos una cita completada con este barbero
            if (! $alreadyReviewed) {
                $canReview = Appointment::where('barber_id', (string) $barber->id)
                    ->where('client_id', (string) $client->id)
                    ->where('estado', 'completada')
                    ->exists();
            }
        }

        return response()->json([
            'barber' => [
                'id' => $barber->id,
                'slug' => $barber->slug,
                'descripcion' => $barber->descripcion,
                'especialidades' => $barber->especialidades,
                'foto' => $barber->foto,
                'user' => $barber->user ? ['id' => $barber->user->id, 'name' => $barber->user->name] : null,
            ],
            'works' => $works->map(fn (Work $w) => [
                'id' => $w->id,
                'title' => $w->title,
                'description' => $w->description,
                'images' => $w->images->map(fn ($img) => asset('storage/'.$img->image))->values(),
            ])->values(),
            'reviews' => $reviews->map(fn (BarberReview $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'created_at' => optional($r->created_at)?->toAtomString(),
                'client' => ['user' => ['name' => $r->client?->user?->name]],
            ])->values(),
            'avg_rating' => $avgRating,
            'total_reviews' => $reviews->count(),
            'citas_completadas' => $citasCompletadas,
            'can_review' => $canReview,
            'already_reviewed' => $alreadyReviewed,
        ]);
    }

    // Crea una reseña de cliente para un barbero (requiere cita completada previa, una sola reseña por cliente/barbero)
    public function storeReview(Barber $barber, Request $request): JsonResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403, 'No tienes perfil de cliente.');

        if (! Appointment::where('barber_id', (string) $barber->id)
            ->where('client_id', (string) $client->id)
            ->where('estado', 'completada')
            ->exists()) {
            return response()->json([
                'message' => 'Solo puedes reseñar barberos con los que hayas tenido una cita completada.',
            ], 422);
        }

        if (BarberReview::where('barber_id', (string) $barber->id)
            ->where('client_id', (string) $client->id)
            ->exists()) {
            return response()->json([
                'message' => 'Ya dejaste una reseña para este barbero.',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $review = BarberReview::create([
            'barber_id' => (string) $barber->id,
            'client_id' => (string) $client->id,
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        // Keep denormalized rating fields on the Barber document in sync
        $allReviews = BarberReview::where('barber_id', (string) $barber->id)->get('rating');
        $barber->calificacion_promedio = round($allReviews->avg('rating'), 2);
        $barber->total_resenas = $allReviews->count();
        $barber->save();

        app(LoyaltyService::class)->awardResenaPoints($client, (string) $review->id);

        return response()->json([
            'message' => '¡Gracias por tu reseña! +5 puntos de lealtad añadidos.',
            'review' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
            ],
        ], 201);
    }
}
