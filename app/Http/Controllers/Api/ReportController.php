<?php

namespace App\Http\Controllers\Api;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'types' => ['ingresos', 'citas', 'inventario', 'clientes'],
            'formats' => ['json', 'pdf', 'excel'],
        ]);
    }

    public function export(Request $request, string $type, string $format)
    {
        $this->authorizeAdmin($request);

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

        return response()->json([
            'title' => $report['title'],
            'headings' => $report['headings'],
            'keys' => $report['keys'],
            'rows' => $report['rows']->values(),
            'filters' => $filters,
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
