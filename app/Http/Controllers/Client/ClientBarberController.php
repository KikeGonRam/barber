<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Comment;
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

        // Works are stored with barbero_id = user_id (not barber _id)
        $works = \App\Models\Work::where('barbero_id', (string) $barber->user_id)
            ->with(['images', 'reactions', 'comments'])
            ->latest()
            ->limit(12)
            ->get();

        $citasCompletadas = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')
            ->count();

        $avgRating = Comment::whereHas('work', fn($q) => $q->where('barbero_id', $barber->user_id))
            ->whereNotNull('rating')
            ->avg('rating');
        $avgRating = $avgRating ? round((float) $avgRating, 1) : null;

        $yearsExp = max(1, (int) $barber->user?->created_at?->diffInYears(now()));

        $disponibleHoy = BarberSchedule::where('barber_id', (string) $barber->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_working', true)
            ->exists();

        return view('client.barbers.show', compact(
            'barber',
            'works',
            'citasCompletadas',
            'avgRating',
            'yearsExp',
            'disponibleHoy',
        ));
    }
}
