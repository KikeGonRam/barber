<?php

namespace App\Http\Controllers\Barber;

use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberAppointmentStatusRequest;
use App\Http\Requests\Barber\UpdateBarberProfileRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Work;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Panel web propio del barbero autenticado: su agenda, cambio de estado de sus citas,
 * su perfil público/portafolio y su horario de trabajo. Uso exclusivo del rol barbero.
 */
class BarberDashboardController extends Controller
{
    /**
     * Agenda del barbero autenticado para el período (día/semana) y offset dados,
     * con estadísticas del período y del filtro de estado aplicado.
     */
    public function agenda(Request $request): View
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $period = $request->string('period')->toString() ?: 'day';
        $estadoFilter = $request->string('estado')->toString() ?: '';
        $dateOffset = (int) $request->input('offset', 0);

        $baseDate = now()->addDays($dateOffset);

        // Carbon objects required — whereBetween with strings fails against MongoDB UTCDateTime
        $periodStart = $period === 'week'
            ? $baseDate->copy()->startOfWeek()->startOfDay()
            : $baseDate->copy()->startOfDay();
        $periodEnd = $period === 'week'
            ? $baseDate->copy()->endOfWeek()->endOfDay()
            : $baseDate->copy()->endOfDay();

        // All appointments in the period (unfiltered) for stats
        $allPeriod = Appointment::query()
            ->where('barber_id', (string) $barber->id)
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->get();

        $totalPeriod = $allPeriod->count();
        $completedPeriod = $allPeriod->where('estado', 'completada')->count();
        $pendingPeriod = $allPeriod->where('estado', 'pendiente')->count();
        $confirmedPeriod = $allPeriod->where('estado', 'confirmada')->count();
        $inProcessPeriod = $allPeriod->where('estado', 'en_proceso')->count();
        $cancelledPeriod = $allPeriod->where('estado', 'cancelada')->count();
        $noShowPeriod = $allPeriod->where('estado', 'no_asistio')->count();
        $productivity = $totalPeriod > 0 ? round($completedPeriod / $totalPeriod * 100) : 0;

        // Filtered agenda list
        $query = Appointment::query()
            ->where('barber_id', (string) $barber->id)
            ->with(['client.user', 'service'])
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($estadoFilter !== '') {
            $query->where('estado', $estadoFilter);
        }

        $agenda = $query->get();

        $stats = [
            'completed_count' => Appointment::query()->where('barber_id', (string) $barber->id)->where('estado', 'completada')->count(),
            'income_total' => (float) Appointment::query()->where('barber_id', (string) $barber->id)->where('estado', 'completada')->sum('precio_cobrado'),
            'productivity' => $productivity,
            'total_period' => $totalPeriod,
            'pending_period' => $pendingPeriod,
            'confirmed_period' => $confirmedPeriod,
            'in_process_period' => $inProcessPeriod,
            'completed_period' => $completedPeriod,
            'cancelled_period' => $cancelledPeriod,
            'no_show_period' => $noShowPeriod,
        ];

        return view('barber.agenda', compact('agenda', 'stats', 'period', 'estadoFilter', 'baseDate', 'dateOffset'));
    }

    /**
     * Cambia el estado de una cita del barbero autenticado (verifica que la cita le pertenezca);
     * al completarla otorga puntos de lealtad y notifica al cliente.
     */
    public function updateAppointmentStatus(UpdateBarberAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        $barber = $request->user()?->barberProfile;
        // La cita debe pertenecer a ESTE barbero — evita que edite citas ajenas por URL directa
        abort_if(! $barber || (string) $appointment->barber_id !== (string) $barber->id, 403);

        $wasCompletada = $appointment->estado === 'completada';
        $nuevoEstado = $request->validated()['estado'];

        // Transicion validada por la maquina de estados (flujo estricto).
        try {
            app(AppointmentStatusService::class)->transition($appointment, $nuevoEstado);
        } catch (InvalidAppointmentTransitionException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }

        if (! empty($request->validated()['notas'])) {
            $appointment->update(['notas' => $request->validated()['notas']]);
        }

        if ($nuevoEstado === 'completada' && ! $wasCompletada) {
            $client = Client::find($appointment->client_id);
            if ($client) {
                app(LoyaltyService::class)->awardCitaPoints($client, (string) $appointment->id);
            }
        }

        // Avisar al cliente del cambio (aprobada, en proceso, completada, etc.).
        app(AppointmentNotifier::class)->statusChanged($appointment, $nuevoEstado);

        return back()->with('status', 'Estado de cita actualizado.');
    }

    /**
     * Perfil público del barbero autenticado (para que edite su bio/foto), con estadísticas
     * reales de citas, rating y últimos trabajos del portafolio.
     */
    public function editProfile(Request $request): View
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $userId = $request->user()->id;

        // Real stats
        $citasTotal = Appointment::where('barber_id', (string) $barber->id)->where('estado', 'completada')->count();
        $citasMes = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')
            ->whereMonth('fecha', now()->month)->count();
        $memberSince = $request->user()->created_at;
        $yearsExp = max(1, (int) $memberSince->diffInYears(now()));

        // Rating promedio desde comentarios de trabajos
        $avgRating = Comment::whereHas('work', fn ($q) => $q->where('barbero_id', $userId))
            ->whereNotNull('rating')
            ->avg('rating');
        $avgRating = $avgRating ? round((float) $avgRating, 1) : null;

        // Últimos 6 trabajos del portfolio
        $portfolioWorks = Work::where('barbero_id', $userId)
            ->with(['images', 'reactions', 'comments'])
            ->latest()
            ->limit(6)
            ->get();

        $portfolioTotal = Work::where('barbero_id', $userId)->count();

        return view('barber.profile', compact(
            'barber',
            'citasTotal',
            'citasMes',
            'yearsExp',
            'avgRating',
            'portfolioWorks',
            'portfolioTotal',
        ));
    }

    /**
     * Formulario de horario semanal del barbero autenticado; si nunca lo configuró,
     * genera un horario por defecto (lunes a sábado 9-21, domingo libre).
     */
    public function editSchedule(Request $request): View
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $schedules = $barber->schedules()->orderBy('day_of_week')->get();

        // If no schedules, create defaults (Mon-Sat 9-21)
        if ($schedules->isEmpty()) {
            for ($i = 0; $i <= 6; $i++) {
                $barber->schedules()->create([
                    'day_of_week' => $i,
                    'start_time' => '09:00:00',
                    'end_time' => '21:00:00',
                    'is_working' => ($i !== 0), // Default Sunday off
                ]);
            }
            $schedules = $barber->schedules()->orderBy('day_of_week')->get();
        }

        return view('barber.schedule', compact('barber', 'schedules'));
    }

    /**
     * Guarda el horario semanal completo del barbero (los 7 días se envían siempre juntos).
     */
    public function updateSchedule(Request $request): RedirectResponse
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $data = $request->validate([
            'schedules' => 'required|array|size:7',
            'schedules.*.start_time' => 'nullable|date_format:H:i',
            'schedules.*.end_time' => 'nullable|date_format:H:i',
            'schedules.*.is_working' => 'nullable|boolean',
        ]);

        foreach ($data['schedules'] as $day => $values) {
            $barber->schedules()->updateOrCreate(
                ['day_of_week' => $day],
                [
                    'start_time' => $values['start_time'] ? $values['start_time'].':00' : null,
                    'end_time' => $values['end_time'] ? $values['end_time'].':00' : null,
                    'is_working' => isset($values['is_working']),
                ]
            );
        }

        return back()->with('status', 'Horario actualizado correctamente.');
    }

    /**
     * Actualiza el perfil del barbero autenticado; si sube una foto nueva, borra la anterior
     * y organiza el archivo en una carpeta por barbero/fecha para evitar colisiones de nombre.
     */
    public function updateProfile(UpdateBarberProfileRequest $request): RedirectResponse
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $payload = $request->validated();

        if ($request->hasFile('foto')) {
            if (! empty($barber->foto) && Storage::disk('public')->exists($barber->foto)) {
                Storage::disk('public')->delete($barber->foto);
            }

            $userId = (string) $request->user()->id;
            $datePath = now()->format('d/m/Y');
            $directory = "barbers/{$userId}/{$datePath}";
            $extension = $request->file('foto')->getClientOriginalExtension();
            $filename = Str::uuid().'.'.$extension;

            $payload['foto'] = $request->file('foto')->storeAs($directory, $filename, 'public');
        }

        $barber->update($payload);

        return back()->with('status', 'Perfil de barbero actualizado.');
    }
}
