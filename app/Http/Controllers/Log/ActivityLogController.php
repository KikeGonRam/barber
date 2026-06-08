<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'log_name', 'event', 'fecha_desde', 'fecha_hasta', 'causer']);
        $logName = (string) ($filters['log_name'] ?? '');
        $search  = trim((string) ($filters['q'] ?? ''));

        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($logName !== '', fn($q) => $q->where('log_name', $logName))
            ->when(!empty($filters['event']),       fn($q) => $q->where('event', $filters['event']))
            ->when(!empty($filters['causer']),      fn($q) => $q->whereHasMorph('causer', '*', fn($c) => $c->where('name', 'like', '%'.$filters['causer'].'%')))
            ->when(!empty($filters['fecha_desde']), fn($q) => $q->whereDate('created_at', '>=', $filters['fecha_desde']))
            ->when(!empty($filters['fecha_hasta']), fn($q) => $q->whereDate('created_at', '<=', $filters['fecha_hasta']))
            ->when($search !== '', fn($q) => $q->where(fn($sub) =>
                $sub->where('description', 'like', "%{$search}%")->orWhere('event', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $logNames = Activity::query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name');
        $events   = Activity::query()->select('event')->distinct()->orderBy('event')->pluck('event')->filter();

        $stats = [
            'total' => Activity::count(),
            'hoy'   => Activity::whereDate('created_at', today())->count(),
            'creates' => Activity::where('event', 'created')->count(),
            'updates' => Activity::where('event', 'updated')->count(),
            'deletes' => Activity::where('event', 'deleted')->count(),
        ];

        return view('logs.index', compact('logs', 'logNames', 'events', 'logName', 'search', 'filters', 'stats'));
    }
}
