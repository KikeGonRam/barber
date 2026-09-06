<?php

namespace App\Http\Controllers\Api\Log;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de auditoría/logs de actividad (administrador e ingeniero,
 * rol de solo lectura). Expone el listado paginado de registros de Spatie
 * Activitylog con búsqueda, filtro por log_name y datos del causante de
 * cada evento.
 */
class LogController extends Controller
{
    // Lista logs de actividad con búsqueda y filtro por log_name, paginados
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || (! $user->hasRole('administrador') && ! $user->hasRole('ingeniero')), 403, 'Solo administradores pueden consultar logs.');

        $search = trim((string) $request->query('q', ''));
        $logName = trim((string) $request->query('log_name', ''));
        $event = trim((string) $request->query('event', ''));
        $causer = trim((string) $request->query('causer', ''));
        $fechaDesde = trim((string) $request->query('fecha_desde', ''));
        $fechaHasta = trim((string) $request->query('fecha_hasta', ''));

        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            // whereHasMorph con wildcard '*' genera un raw Expression que el
            // driver de MongoDB no sabe convertir a string — a diferencia de
            // la version web (SQL), aqui se resuelve primero los IDs de
            // usuario que matchean y se filtra causer_id directamente (el
            // causante siempre es un User en este sistema).
            ->when($causer !== '', function ($query) use ($causer): void {
                $causerIds = User::where('name', 'like', "%{$causer}%")->pluck('id')->map(fn ($id) => (string) $id)->all();
                $query->whereIn('causer_id', $causerIds);
            })
            ->when($fechaDesde !== '', fn ($query) => $query->whereDate('created_at', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn ($query) => $query->whereDate('created_at', '<=', $fechaHasta))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('log_name', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // distinct()->pluck() no funciona con el driver de MongoDB (devuelve
        // solo null por cada documento en vez de los valores únicos); se
        // deduplica en PHP con unique() en su lugar.
        $logNames = Activity::query()
            ->pluck('log_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $events = Activity::query()
            ->pluck('event')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $stats = [
            'total' => Activity::count(),
            'hoy' => Activity::whereDate('created_at', today())->count(),
            'creates' => Activity::where('event', 'created')->count(),
            'updates' => Activity::where('event', 'updated')->count(),
            'deletes' => Activity::where('event', 'deleted')->count(),
        ];

        return response()->json([
            'data' => $logs->getCollection()->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'properties' => $activity->properties?->toArray() ?? [],
                'created_at' => optional($activity->created_at)?->toAtomString(),
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'q' => $search,
                'log_name' => $logName,
                'event' => $event,
                'causer' => $causer,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'log_names' => $logNames,
            'events' => $events,
            'stats' => $stats,
        ]);
    }
}
