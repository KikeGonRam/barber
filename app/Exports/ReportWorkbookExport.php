<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro de Excel corporativo de un reporte: Portada, Resumen (introducción, indicadores, hallazgos y
 * gráfica nativa) y Detalle (la tabla completa, sin recorte). Los tres comparten los mismos datos que el
 * PDF, de modo que un número nunca difiere entre formatos.
 */
class ReportWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array{period: string, purpose: string, scope: array, kpis: array, findings: array}  $narrative
     * @param  array{title?: string, labels?: array, values?: array, unit?: string}|null  $chart
     */
    public function __construct(
        private readonly string $title,
        private readonly string $folio,
        private readonly array $narrative,
        private readonly Collection $rows,
        private readonly array $headings,
        private readonly array $keys,
        private readonly ?array $chart,
    ) {}

    public function sheets(): array
    {
        return [
            new ReportCoverSheet($this->title, $this->folio, $this->narrative['period']),
            new ReportSummarySheet($this->title, $this->narrative, $this->chart),
            new GenericReportExport($this->rows, $this->headings, 'Detalle', null, $this->keys),
        ];
    }
}
