<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberRequest;
use App\Models\Barber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BarberController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'activo', 'especialidad']);
        $search  = trim((string) ($filters['q'] ?? ''));
        $status  = (string) ($filters['activo'] ?? '');

        $barbers = Barber::query()
            ->with(['user:id,name,email'])
            ->withCount(['appointments as citas_mes' => fn($q) => $q
                ->where('estado', 'completada')
                ->whereMonth('fecha', now()->month)])
            ->when($search !== '', fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== '', fn($q) => $q->where('activo', $status === '1'))
            ->when(!empty($filters['especialidad']), fn($q) => $q->where('especialidades', 'like', '%'.$filters['especialidad'].'%'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total'    => Barber::count(),
            'activos'  => Barber::where('activo', true)->count(),
            'inactivos'=> Barber::where('activo', false)->count(),
        ];

        return view('barbers.index', compact('barbers', 'filters', 'search', 'status', 'stats'));
    }

    public function performance(Barber $barber): \Illuminate\View\View
    {
        $barber->load('user');
        $today      = \Carbon\Carbon::today();
        $monthStart = \Carbon\Carbon::now()->startOfMonth();
        $monthEnd   = \Carbon\Carbon::now()->endOfMonth();

        $stats = [
            'citas_hoy'    => \App\Models\Appointment::where('barber_id', $barber->id)->whereDate('fecha', $today)->count(),
            'citas_mes'    => \App\Models\Appointment::where('barber_id', $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd])->where('estado', 'completada')->count(),
            'ingresos_mes' => \App\Models\Payment::whereHas('appointment', fn($q) => $q->where('barber_id', $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd]))->sum(\Illuminate\Support\Facades\DB::raw('monto + propina')),
            'canceladas_mes' => \App\Models\Appointment::where('barber_id', $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd])->where('estado', 'cancelada')->count(),
            'total_historico' => \App\Models\Appointment::where('barber_id', $barber->id)->where('estado', 'completada')->count(),
        ];

        // Top 5 servicios del barbero
        $topServices = \App\Models\Appointment::where('barber_id', $barber->id)
            ->where('estado', 'completada')
            ->selectRaw('service_id, COUNT(*) as total')
            ->with('service:id,nombre')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Últimas 10 citas
        $recentAppointments = \App\Models\Appointment::where('barber_id', $barber->id)
            ->with(['client.user:id,name', 'service:id,nombre'])
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')
            ->limit(10)->get();

        // Chart: citas por día últimas 4 semanas
        $weeklyChart = collect(range(27, 0))->map(function($offset) use ($barber) {
            $date = \Carbon\Carbon::now()->subDays($offset);
            return [
                'label' => $date->format('d M'),
                'count' => \App\Models\Appointment::where('barber_id', $barber->id)
                    ->whereDate('fecha', $date)->where('estado', 'completada')->count(),
            ];
        });

        return view('barbers.performance', compact('barber', 'stats', 'topServices', 'recentAppointments', 'weeklyChart'));
    }

    public function edit(Barber $barber): View
    {
        $barber->load('user:id,name,email');

        return view('barbers.edit', compact('barber'));
    }

    public function show(Barber $barber): View
    {
        $barber->load([
            'user:id,name,email,created_at',
            'works' => fn($q) => $q->latest()->limit(9),
            'works.images',
            'works.reactions',
            'works.comments',
        ]);

        // Stats reales
        $citasCompletadas = \App\Models\Appointment::where('barber_id', $barber->id)
            ->where('estado', 'completada')->count();

        $avgRating = \App\Models\Comment::whereHas('work', fn($q) =>
            $q->where('barbero_id', $barber->user_id))
            ->whereNotNull('rating')
            ->avg('rating');
        $avgRating = $avgRating ? round((float) $avgRating, 1) : null;

        $yearsExp = max(1, (int) $barber->user?->created_at?->diffInYears(now()));

        // Disponibilidad hoy según schedule real
        $todayDow       = now()->dayOfWeek; // 0=Dom … 6=Sáb (Carbon)
        $disponibleHoy  = \App\Models\BarberSchedule::where('barber_id', $barber->id)
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

    public function update(UpdateBarberRequest $request, Barber $barber): RedirectResponse
    {
        $data = $request->validated();

        $barber->user()->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        $payload = [
            'especialidades' => $data['especialidades'] ?? null,
            'descripcion'    => $data['descripcion'] ?? null,
            'activo'         => (bool) ($data['activo'] ?? false),
        ];

        if ($request->hasFile('foto')) {
            if (! empty($barber->foto) && Storage::disk('public')->exists($barber->foto)) {
                Storage::disk('public')->delete($barber->foto);
            }
            $ext  = $request->file('foto')->getClientOriginalExtension();
            $path = $request->file('foto')->storeAs(
                'barbers/'.$barber->id,
                Str::uuid().'.'.$ext,
                'public'
            );
            $payload['foto'] = $path;
        }

        $barber->update($payload);

        return redirect()->route('barbers.index')->with('status', 'Barbero actualizado correctamente.');
    }
}
