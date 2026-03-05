<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logName = (string) $request->query('log_name', '');
        $search = trim((string) $request->query('q', ''));

        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $logNames = Activity::query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name');

        return view('logs.index', compact('logs', 'logNames', 'logName', 'search'));
    }
}
