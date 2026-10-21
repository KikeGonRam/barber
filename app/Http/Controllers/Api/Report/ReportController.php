<?php

namespace App\Http\Controllers\Api\Report;

use App\Exports\ReportWorkbookExport;
use App\Http\Controllers\Controller;
use App\Services\Report\ReportNarrative;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * API de reportes (ingresos, citas, inventario, clientes) para consumo desde la app
 * móvil/SPA por administradores. Reutiliza ReportService, compartido con el controlador web.
 */
class ReportController extends Controller
{
    // DomPDF no puede manejar miles de filas — mismo limite que el
    // controlador web (Report/ReportController::PDF_ROW_LIMIT).
    private const PDF_ROW_LIMIT = 500;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportNarrative $narrative,
    ) {}

    /**
     * Encabezado y pie de las páginas interiores (la portada, página 1, va limpia): marca y título arriba,
     * folio, confidencialidad y "Página n de N" abajo. Se dibuja sobre el canvas ya renderizado.
     */
    private function stampPages(\Barryvdh\DomPDF\PDF $pdf, string $title, string $folio): void
    {
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('helvetica', 'bold');
        $gold = [0.72, 0.58, 0.12];
        $gray = [0.5, 0.5, 0.5];

        // Márgenes de la hoja: 44px de CSS = 33pt en el canvas, para alinear con el contenido.
        $margin = 33;
        $canvas->page_script(function (int $page, int $count, $canvas, $metrics) use ($font, $title, $folio, $gold, $gray, $margin) {
            if ($page < 2) {
                return;
            }
            $width = $canvas->get_width();
            $height = $canvas->get_height();
            $right = fn (string $text, float $size) => $width - $margin - $metrics->getTextWidth($text, $font, $size, 0, 1);
            $canvas->text($margin, 34, 'URBANBLADE', $font, 8, $gold, 0, 2);
            $header = mb_strtoupper($title);
            $canvas->text($right($header, 7), 34, $header, $font, 7, $gray, 0, 1);
            $canvas->line($margin, 46, $width - $margin, 46, $gold, 0.8);
            $canvas->line($margin, $height - 44, $width - $margin, $height - 44, [0.88, 0.88, 0.88], 0.5);
            $canvas->text($margin, $height - 32, 'CONFIDENCIAL · '.$folio, $font, 6.5, $gray, 0, 1);
            $label = sprintf('Página %d de %d', $page, $count);
            $canvas->text($right($label, 7), $height - 32, $label, $font, 7, $gray);
        });
    }

    /**
     * Lista los tipos de reporte y formatos de exportación disponibles.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'types' => ['ingresos', 'citas', 'inventario', 'clientes'],
            'formats' => ['json', 'pdf', 'excel'],
        ]);
    }

    /**
     * Genera un reporte del tipo indicado y lo devuelve en el formato solicitado
     * (json, excel descargable o pdf con recorte de filas).
     */
    public function export(Request $request, string $type, string $format)
    {
        $this->authorizeAdmin($request);

        $filters = $request->only(['start_date', 'end_date', 'barber_id', 'metodo_pago', 'estado', 'categoria', 'tipo']);
        $report = $this->reportService->reportByType($type, $filters);

        $narrative = in_array($format, ['excel', 'pdf'], true) ? $this->narrative->build($type, $report, $filters) : [];
        $folio = sprintf('UB-%s-%s-%s', strtoupper(substr($type, 0, 3)), now()->format('Ymd'), strtoupper(Str::random(4)));

        if ($format === 'excel') {
            $rows = $report['rows']->map(fn ($row) => collect($report['keys'])->map(fn ($key) => $row[$key] ?? null)->all());

            return Excel::download(
                new ReportWorkbookExport($report['title'], $folio, $narrative, $rows, $report['headings'], $report['keys'], $report['chart'] ?? null),
                sprintf('%s-%s.xlsx', $type, now()->format('Ymd_His'))
            );
        }

        if ($format === 'pdf') {
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
                'filters' => $filters,
                'trimmed' => $trimmed,
                'pdf_limit' => self::PDF_ROW_LIMIT,
                'total' => $report['rows']->count(),
                'chart' => $report['chart'] ?? null,
                'narrative' => $narrative,
                'folio' => $folio,
                'preparedBy' => $request->user()?->name,
            ]);
            $this->stampPages($pdf, $report['title'], $folio);

            return $pdf->download(sprintf('%s-%s.pdf', $type, now()->format('Ymd_His')));
        }

        return response()->json([
            'title' => $report['title'],
            'headings' => $report['headings'],
            'keys' => $report['keys'],
            'rows' => $report['rows']->values(),
            'filters' => $filters,
            // Misma gráfica que ya lleva el PDF (título, etiquetas, valores y unidad); aditivo.
            'chart' => $report['chart'] ?? null,
        ]);
    }

    /**
     * Restringe el acceso a administradores e ingeniero (rol de solo
     * lectura -- puede consultar/exportar reportes, nunca gestionarlos).
     * El resto recibe 403.
     */
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || (! $user->hasRole('administrador') && ! $user->hasRole('ingeniero')), 403, 'Solo administradores pueden consultar reportes.');
    }
}
