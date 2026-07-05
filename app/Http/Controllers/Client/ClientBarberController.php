<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\BarberSchedule;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientBarberController extends Controller
{
    public function index(): View
    {
        $barbers = Barber::query()
            ->where('activo', true)
            ->with('user:id,name,created_at')
            ->get();

        $barberIds = $barbers->pluck('id')->map(fn($id) => (string) $id)->toArray();

        if (! empty($barberIds)) {
            $citasCounts = Appointment::whereIn('barber_id', $barberIds)
                ->where('estado', 'completada')
                ->get(['barber_id'])
                ->groupBy('barber_id')
                ->map->count();

            $barbers->each(fn($b) => $b->citas_completadas = $citasCounts->get((string) $b->id, 0));
        }

        return view('client.barbers.index', compact('barbers'));
    }

    public function show(Barber $barber): View
    {
        $barber->load('user:id,name,email,created_at');

        $works = \App\Models\Work::where('barbero_id', (string) $barber->user_id)
            ->with(['images', 'reactions', 'comments'])
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

        $avgRating     = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;
        $totalReviews  = $reviews->count();
        $ratingCounts  = $reviews->groupBy('rating')->map->count();

        $yearsExp = max(1, (int) $barber->user?->created_at?->diffInYears(now()));

        $disponibleHoy = BarberSchedule::where('barber_id', (string) $barber->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_working', true)
            ->exists();

        $client          = auth()->user()?->clientProfile;
        $alreadyReviewed = false;
        $canReview       = false;

        if ($client) {
            $alreadyReviewed = BarberReview::where('barber_id', (string) $barber->id)
                ->where('client_id', (string) $client->id)
                ->exists();

            if (! $alreadyReviewed) {
                $canReview = Appointment::where('barber_id', (string) $barber->id)
                    ->where('client_id', (string) $client->id)
                    ->where('estado', 'completada')
                    ->exists();
            }
        }

        return view('client.barbers.show', compact(
            'barber', 'works', 'citasCompletadas',
            'reviews', 'avgRating', 'totalReviews', 'ratingCounts',
            'yearsExp', 'disponibleHoy', 'canReview', 'alreadyReviewed',
        ));
    }

    public function storeReview(Request $request, Barber $barber): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403);

        if (! Appointment::where('barber_id', (string) $barber->id)
                ->where('client_id', (string) $client->id)
                ->where('estado', 'completada')
                ->exists()) {
            return back()->withErrors(['rating' => 'Solo puedes reseñar barberos con los que hayas tenido una cita completada.']);
        }

        if (BarberReview::where('barber_id', (string) $barber->id)
                ->where('client_id', (string) $client->id)
                ->exists()) {
            return back()->withErrors(['rating' => 'Ya dejaste una reseña para este barbero.']);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = BarberReview::create([
            'barber_id' => (string) $barber->id,
            'client_id' => (string) $client->id,
            'rating'    => (int) $validated['rating'],
            'comment'   => $validated['comment'] ?? null,
        ]);

        app(LoyaltyService::class)->awardResenaPoints($client, (string) $review->id);

        return back()->with('status', '¡Gracias por tu reseña! +5 puntos de lealtad añadidos.');
    }
}
