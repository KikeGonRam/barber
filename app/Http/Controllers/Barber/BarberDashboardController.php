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

        $period = $request->string('period')->toString() ?: 'day';

        $query = Appointment::query()
            ->where('barber_id', $barber->id)
            ->with(['client.user', 'service'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($period === 'week') {
            $query->whereBetween('fecha', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);
        } else {
            $query->whereDate('fecha', now()->toDateString());
        }

        $agenda = $query->get();

        $stats = [
            'completed_count' => Appointment::query()->where('barber_id', $barber->id)->where('estado', 'completada')->count(),
            'income_total' => (float) Appointment::query()->where('barber_id', $barber->id)->where('estado', 'completada')->sum('precio_cobrado'),
        ];

        return view('barber.agenda', compact('agenda', 'stats', 'period'));
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

        return view('barber.profile', compact('barber'));
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
