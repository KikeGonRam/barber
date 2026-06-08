<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberAppointmentStatusRequest;
use App\Http\Requests\Barber\UpdateBarberProfileRequest;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BarberDashboardController extends Controller
{
    public function agenda(Request $request): View
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $period      = $request->string('period')->toString() ?: 'day';
        $estadoFilter = $request->string('estado')->toString() ?: '';
        $dateOffset  = (int) $request->input('offset', 0);

        $baseDate = now()->addDays($dateOffset);

        $periodStart = $period === 'week'
            ? $baseDate->copy()->startOfWeek()->toDateString()
            : $baseDate->toDateString();
        $periodEnd = $period === 'week'
            ? $baseDate->copy()->endOfWeek()->toDateString()
            : $baseDate->toDateString();

        // All appointments in the period (unfiltered) for stats
        $allPeriod = Appointment::query()
            ->where('barber_id', $barber->id)
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->get();

        $totalPeriod     = $allPeriod->count();
        $completedPeriod = $allPeriod->where('estado', 'completada')->count();
        $pendingPeriod   = $allPeriod->where('estado', 'pendiente')->count();
        $inProcessPeriod = $allPeriod->where('estado', 'en_proceso')->count();
        $cancelledPeriod = $allPeriod->where('estado', 'cancelada')->count();
        $productivity    = $totalPeriod > 0 ? round($completedPeriod / $totalPeriod * 100) : 0;

        // Filtered agenda list
        $query = Appointment::query()
            ->where('barber_id', $barber->id)
            ->with(['client.user', 'service'])
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($estadoFilter !== '') {
            $query->where('estado', $estadoFilter);
        }

        $agenda = $query->get();

        $stats = [
            'completed_count'  => Appointment::query()->where('barber_id', $barber->id)->where('estado', 'completada')->count(),
            'income_total'     => (float) Appointment::query()->where('barber_id', $barber->id)->where('estado', 'completada')->sum('precio_cobrado'),
            'productivity'     => $productivity,
            'total_period'     => $totalPeriod,
            'pending_period'   => $pendingPeriod,
            'in_process_period'=> $inProcessPeriod,
            'completed_period' => $completedPeriod,
            'cancelled_period' => $cancelledPeriod,
        ];

        return view('barber.agenda', compact('agenda', 'stats', 'period', 'estadoFilter', 'baseDate', 'dateOffset'));
    }

    public function updateAppointmentStatus(UpdateBarberAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber || $appointment->barber_id !== $barber->id, 403);

        $appointment->update([
            'estado' => $request->validated()['estado'],
            'notas' => $request->validated()['notas'] ?? $appointment->notas,
        ]);

        return back()->with('status', 'Estado de cita actualizado.');
    }

    public function editProfile(Request $request): View
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403);

        $userId = $request->user()->id;

        // Real stats
        $citasTotal     = \App\Models\Appointment::where('barber_id', $barber->id)->where('estado', 'completada')->count();
        $citasMes       = \App\Models\Appointment::where('barber_id', $barber->id)
            ->where('estado', 'completada')
            ->whereMonth('fecha', now()->month)->count();
        $memberSince    = $request->user()->created_at;
        $yearsExp       = max(1, (int) $memberSince->diffInYears(now()));

        // Rating promedio desde comentarios de trabajos
        $avgRating = \App\Models\Comment::whereHas('work', fn($q) => $q->where('barbero_id', $userId))
            ->whereNotNull('rating')
            ->avg('rating');
        $avgRating = $avgRating ? round((float) $avgRating, 1) : null;

        // Últimos 6 trabajos del portfolio
        $portfolioWorks = \App\Models\Work::where('barbero_id', $userId)
            ->with(['images', 'reactions', 'comments'])
            ->latest()
            ->limit(6)
            ->get();

        $portfolioTotal = \App\Models\Work::where('barbero_id', $userId)->count();

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
