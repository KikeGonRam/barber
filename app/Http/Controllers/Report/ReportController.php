<?php

namespace App\Http\Controllers\Report;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    // DomPDF no puede manejar miles de filas — limitar a 500 en PDF
    private const PDF_ROW_LIMIT = 500;

    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request)
    {
        $barbers = Barber::query()->with('user:id,name')->get(['id', 'user_id']);

        $filters = $request->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);

        $charts = [];
        foreach (['ingresos', 'citas', 'inventario', 'clientes'] as $type) {
            $charts[$type] = $this->reportService->reportByType($type, $filters)['chart'] ?? null;
        }

        return view('reports.index', compact('barbers', 'charts'));
    }

    public function export(Request $request, string $type, string $format)
    {
        // Tiempo y memoria extra para reportes grandes
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $filters = $request->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);
        $report = $this->reportService->reportByType($type, $filters);
        $filename = sprintf('%s-%s', $type, now()->format('Ymd_His'));

        return match ($format) {
            'excel' => $this->downloadExcel($report, $filename),
            'pdf' => $this->downloadPdf($report, $filename),
            'csv' => $this->downloadCsv($report, $filename),
            default => abort(404, 'Formato no soportado.'),
        };
    }

    // ── Formatos ─────────────────────────────────────────────────────────────

    private function downloadExcel(array $report, string $filename): BinaryFileResponse
    {
        $rows = $report['rows']->map(
            fn ($row) => collect($report['keys'])->map(fn ($key) => $row[$key] ?? null)->all()
        );

        return Excel::download(
            new GenericReportExport($rows, $report['headings'], $report['title'], $report['chart'] ?? null),
            "{$filename}.xlsx"
        );
    }

    private function downloadPdf(array $report, string $filename): Response
    {
        $rows = $report['rows'];
        $trimmed = false;

        if ($rows->count() > self::PDF_ROW_LIMIT) {
            $rows = $rows->take(self::PDF_ROW_LIMIT);
            $trimmed = true;
        }

        $pdf = Pdf::loadView('reports.pdf-table', [
            'title' => $report['title'],
            'headings' => $report['headings'],
            'rows' => $rows,
            'keys' => $report['keys'],
            'filters' => [],
            'trimmed' => $trimmed,
            'pdf_limit' => self::PDF_ROW_LIMIT,
            'total' => $report['rows']->count(),
            'chart' => $report['chart'] ?? null,
        ]);

        $pdf->setPaper('letter', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    private function downloadCsv(array $report, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel abra el CSV con tildes correctamente
            fwrite($handle, "\xEF\xBB\xBF");

            // Encabezados
            fputcsv($handle, $report['headings'], ',', '"');

            // Filas
            foreach ($report['rows'] as $row) {
                $line = array_map(fn ($key) => $row[$key] ?? '', $report['keys']);
                fputcsv($handle, $line, ',', '"');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
