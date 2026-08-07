<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Comment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Panel web de administración de barberos (alta administrativa se hace en otro flujo):
 * listado, rendimiento, ficha pública y edición de perfil. Uso de administración/recepción.
 */
class BarberController extends Controller
{
    /**
     * Listado paginado de barberos con filtros (nombre/email, activo, especialidad)
     * y conteo de citas completadas del mes calculado en PHP (MongoDB no soporta withCount).
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'activo', 'especialidad']);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['activo'] ?? '');

        $barbers = Barber::query()
            ->with(['user:id,name,email'])
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('activo', $status === '1'))
            ->when(! empty($filters['especialidad']), fn ($q) => $q->where('especialidades', 'like', '%'.$filters['especialidad'].'%'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // withCount not supported by MongoDB — compute PHP-side after pagination
        $barberIds = $barbers->pluck('id')->toArray();
        if (! empty($barberIds)) {
            $citasCounts = Appointment::whereIn('barber_id', $barberIds)
                ->where('estado', 'completada')
                ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
                ->get(['barber_id'])
                ->groupBy('barber_id')
                ->map->count();
            $barbers->each(fn ($b) => $b->citas_mes = $citasCounts->get($b->id, 0));
        }

        $stats = [
            'total' => Barber::count(),
            'activos' => Barber::where('activo', true)->count(),
            'inactivos' => Barber::where('activo', false)->count(),
        ];

        return view('barbers.index', compact('barbers', 'filters', 'search', 'status', 'stats'));
    }

    /**
     * Ficha de rendimiento de un barbero: estadísticas del mes, top de servicios,
     * últimas citas y gráfica de citas completadas de las últimas 4 semanas.
     */
    public function performance(Barber $barber): View
    {
        $barber->load('user');
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $stats = [
            'citas_hoy' => Appointment::where('barber_id', $barber->id)->whereDate('fecha', $today)->count(),
            'citas_mes' => Appointment::where('barber_id', $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd])->where('estado', 'completada')->count(),
            'ingresos_mes' => (float) Payment::whereHas('appointment', fn ($q) => $q->where('barber_id', (string) $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd]))->get(['monto', 'propina'])->sum(fn ($p) => (float) $p->monto + (float) $p->propina),
            'canceladas_mes' => Appointment::where('barber_id', $barber->id)->whereBetween('fecha', [$monthStart, $monthEnd])->where('estado', 'cancelada')->count(),
            'total_historico' => Appointment::where('barber_id', $barber->id)->where('estado', 'completada')->count(),
        ];

        // Top 5 servicios del barbero
        $topServices = Appointment::where('barber_id', $barber->id)
            ->where('estado', 'completada')
            ->with('service:id,nombre')
            ->get(['service_id'])
            ->groupBy('service_id')
            ->map(fn ($group) => (object) ['service' => $group->first()->service, 'total' => $group->count()])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        // Últimas 10 citas
        $recentAppointments = Appointment::where('barber_id', $barber->id)
            ->with(['client.user:id,name', 'service:id,nombre'])
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')
            ->limit(10)->get();

        // Chart: citas por día últimas 4 semanas
        $weeklyChart = collect(range(27, 0))->map(function ($offset) use ($barber) {
            $date = Carbon::now()->subDays($offset);

            return [
                'label' => $date->format('d M'),
                'count' => Appointment::where('barber_id', $barber->id)
                    ->whereDate('fecha', $date)->where('estado', 'completada')->count(),
            ];
        });

        return view('barbers.performance', compact('barber', 'stats', 'topServices', 'recentAppointments', 'weeklyChart'));
    }

    /**
     * Formulario de edición de datos de un barbero.
     */
    public function edit(Barber $barber): View
    {
        $barber->load('user:id,name,email');

        return view('barbers.edit', compact('barber'));
    }

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

        $avgRating = Comment::whereHas('work', fn ($q) => $q->where('barbero_id', $barber->user_id))
            ->whereNotNull('rating')
            ->avg('rating');
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

    /**
     * Actualiza los datos del usuario y perfil del barbero, incluyendo el reemplazo
     * de su foto en el disco público si se sube una nueva.
     */
    public function update(UpdateBarberRequest $request, Barber $barber): RedirectResponse
    {
        $data = $request->validated();

        $barber->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $payload = [
            'especialidades' => $data['especialidades'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ];

        if ($request->hasFile('foto')) {
            if (! empty($barber->foto) && Storage::disk('public')->exists($barber->foto)) {
                Storage::disk('public')->delete($barber->foto);
            }
            $ext = $request->file('foto')->getClientOriginalExtension();
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
