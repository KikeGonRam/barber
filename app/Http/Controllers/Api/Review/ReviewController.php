<?php

namespace App\Http\Controllers\Api\Review;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\BarberReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de administración de reseñas de clientes a barberos (Fase Muro/Reseñas),
 * puerto de Barber\ReviewController (web): mismos filtros (barbero/rating),
 * mismo orden, mismas stats. No existía ninguna API para esto antes.
 */
class ReviewController extends Controller
{
    use Sortable;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');

        $filters = $request->only(['barber_id', 'rating']);

        $query = BarberReview::query()
            ->with(['barber.user:id,name', 'client.user:id,name'])
            ->when(! empty($filters['barber_id']), fn ($q) => $q->where('barber_id', $filters['barber_id']))
            ->when(! empty($filters['rating']), fn ($q) => $q->where('rating', (int) $filters['rating']));

        $reviews = $this->applySort($query, $request, ['created_at', 'rating'], 'created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $barbers = Barber::query()->with('user:id,name')->orderBy('nombre')->get(['id', 'user_id', 'nombre']);

        $stats = [
            'total' => BarberReview::count(),
            'promedio' => round((float) BarberReview::avg('rating'), 1) ?: 0,
            'bajas' => BarberReview::where('rating', '<=', 2)->count(),
        ];

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (BarberReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => optional($review->created_at)->toAtomString(),
                'barber' => ['id' => $review->barber?->id, 'name' => $review->barber?->user?->name],
                'client' => ['id' => $review->client?->id, 'name' => $review->client?->user?->name],
            ])->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
            'filters' => [
                'barber_id' => $filters['barber_id'] ?? null,
                'rating' => $filters['rating'] ?? null,
            ],
            'barbers' => $barbers->map(fn (Barber $b) => ['id' => $b->id, 'name' => $b->user?->name ?? $b->nombre])->values(),
            'stats' => $stats,
        ]);
    }
}
