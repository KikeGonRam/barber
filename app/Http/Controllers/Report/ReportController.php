<?php

namespace App\Http\Controllers\Report;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index()
    {
        $barbers = Barber::query()->with('user:id,name')->get(['id', 'user_id']);

        return view('reports.index', compact('barbers'));
    }

    public function export(Request $request, string $type, string $format)
    {
        $filters = $request->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);
        $report = $this->reportService->reportByType($type, $filters);

        if ($format === 'excel') {
            $rows = $report['rows']->map(fn ($row) => collect($report['keys'])->map(fn ($key) => $row[$key] ?? null)->all());

            return Excel::download(
                new GenericReportExport($rows, $report['headings'], $report['title']),
                sprintf('%s-%s.xlsx', $type, now()->format('Ymd_His'))
            );
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf-table', [
                'title' => $report['title'],
                'headings' => $report['headings'],
                'rows' => $report['rows'],
                'keys' => $report['keys'],
                'filters' => $filters,
            ]);

            return $pdf->download(sprintf('%s-%s.pdf', $type, now()->format('Ymd_His')));
        }

        abort(404);
    }
}
