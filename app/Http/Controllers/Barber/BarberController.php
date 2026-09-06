<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\BarberSchedule;
use Illuminate\View\View;

/**
 * Ficha pública de un barbero (sin autenticación). El listado/edición/
 * rendimiento administrativo se retiró de Blade: Nuxt (frontend-urban)
 * tiene paridad funcional confirmada para esas pantallas — ver
 * Api\Admin\Barber\BarberAdminController para el equivalente JSON.
 */
class BarberController extends Controller
{
    /**
     * Ficha pública del barbero (portafolio de trabajos, rating, disponibilidad de hoy)
     * mostrada a clientes.
     */
    public function show(Barber $barber): View
    {
        $barber->load([
            'user:id,name,email,created_at',
            'works' => fn ($q) => $q->latest()->limit(9),
            'works.images',
            'works.reactions',
            'works.comments',
        ]);

        // Stats reales
        $citasCompletadas = Appointment::where('barber_id', $barber->id)
            ->where('estado', 'completada')->count();

        // BarberReview (reseñas reales de clientes), no Comment (comentarios del muro social) —
        // ver el mismo bug ya corregido en BarberAdminController::calculateRating().
        $avgRating = BarberReview::where('barber_id', (string) $barber->id)->avg('rating');
        $avgRating = $avgRating ? round((float) $avgRating, 1) : null;

        $yearsExp = max(1, (int) $barber->user?->created_at?->diffInYears(now()));

        // Disponibilidad hoy según schedule real
        $todayDow = now()->dayOfWeek; // 0=Dom … 6=Sáb (Carbon)
        $disponibleHoy = BarberSchedule::where('barber_id', $barber->id)
            ->where('day_of_week', $todayDow)
            ->where('is_working', true)
            ->exists();

        return view('barbers.public-show', compact(
            'barber',
            'citasCompletadas',
            'avgRating',
            'yearsExp',
            'disponibleHoy',
        ));
    }
}
