<?php

namespace App\Http\Controllers\Api\Log;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de auditoría/logs de actividad (solo administrador).
 * Expone el listado paginado de registros de Spatie Activitylog con
 * búsqueda, filtro por log_name y datos del causante de cada evento.
 */
class LogController extends Controller
{
    // Lista logs de actividad con búsqueda y filtro por log_name, paginados
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'Solo administradores pueden consultar logs.');

        $search = trim((string) $request->query('q', ''));
        $logName = trim((string) $request->query('log_name', ''));

        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
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
            ],
            'log_names' => $logNames,
        ]);
    }
}
