<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\BarberReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado de reseñas de clientes a barberos para administración: antes no
 * existía ninguna pantalla para ver reseñas individuales (solo el promedio
 * agregado en el dashboard de desempeño), así que un admin avisado de una
 * reseña mala (ver BarberReviewFlaggedNotification) no tenía dónde ir a
 * leerla dentro del sistema.
 */
class ReviewController extends Controller
{
    use Sortable;

    public function index(Request $request): View
    {
        $filters = $request->only(['barber_id', 'rating']);

        $query = BarberReview::query()
            ->with(['barber.user:id,name', 'client.user:id,name'])
            ->when(! empty($filters['barber_id']), fn ($q) => $q->where('barber_id', $filters['barber_id']))
            ->when(! empty($filters['rating']), fn ($q) => $q->where('rating', (int) $filters['rating']));

        $reviews = $this->applySort($query, $request, ['created_at', 'rating'], 'created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $barbers = Barber::query()->with('user:id,name')->orderBy('nombre')->get(['user_id', 'nombre']);

        $stats = [
            'total' => BarberReview::count(),
            'promedio' => round((float) BarberReview::avg('rating'), 1) ?: 0,
            'bajas' => BarberReview::where('rating', '<=', 2)->count(),
        ];

        return view('reviews.index', compact('reviews', 'filters', 'barbers', 'stats'));
    }
}
