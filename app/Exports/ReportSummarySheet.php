<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja "Resumen": introducción, alcance, indicadores clave en tarjetas, hallazgos redactados y la gráfica
 * nativa (editable) del reporte. Sus datos fuente viven en N:O, fuera de la zona de lectura.
 */
class ReportSummarySheet implements WithCharts, WithEvents, WithTitle
{
    private const PALETTE = ['D4AF37', '2A9D8F', '4C78C9', '8E6BBF', 'E07A3F', '5AA469', 'C8556D', '7A8793'];

    private const GOLD_TEXT = 'B8941F';

    /**
     * @param  array{period: string, purpose: string, scope: array, kpis: array, findings: array}  $narrative
     * @param  array{title?: string, labels?: array, values?: array, unit?: string, x_label?: string, y_label?: string}|null  $chart
     */
    public function __construct(
        private readonly string $title,
        private readonly array $narrative,
        private readonly ?array $chart,
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    /** Filas que ocupa cada bloque; dependen solo de cuántos renglones trae el contenido. */
    private function layout(): array
    {
        $scopeStart = 8;
        $kpiHeader = $scopeStart + 1 + count($this->narrative['scope']) + 1;
        $findingsHeader = $kpiHeader + 5;
        $chartHeader = $findingsHeader + 1 + count($this->narrative['findings']) + 1;

        return ['scope' => $scopeStart, 'kpi' => $kpiHeader, 'findings' => $findingsHeader, 'chart' => $chartHeader];
    }

    private function hasChart(): bool
    {
        return ! empty($this->chart['labels']) && ! empty($this->chart['values']);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rows = $this->layout();
                $sheet->setShowGridlines(false);
                $sheet->getTabColor()->setRGB('D4AF37');
                foreach (range('A', 'L') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(13);
                }
                $sheet->getColumnDimension('N')->setWidth(24);
                $sheet->getColumnDimension('O')->setWidth(14);

                $this->banner($sheet);
                $this->introduction($sheet, $rows['scope']);
                $this->kpis($sheet, $rows['kpi']);
                $this->findings($sheet, $rows['findings']);
                $this->chartSource($sheet, $rows['chart']);
            },
        ];
    }

    private function banner(Worksheet $sheet): void
    {
        $sheet->mergeCells('A1:L2');
        $sheet->setCellValue('A1', 'Resumen ejecutivo  ·  '.$this->title);
        $sheet->getStyle('A1:L2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0A0A0A');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB('D4AF37');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(18);
    }

    private function sectionHeader(Worksheet $sheet, int $row, string $text): void
    {
        $sheet->mergeCells("A{$row}:L{$row}");
        $sheet->setCellValue('A'.$row, $text);
        $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('D4AF37');
        $sheet->getStyle('A'.$row)->getFont()->setSize(10)->setBold(true)->getColor()->setRGB(self::GOLD_TEXT);
    }

    private function introduction(Worksheet $sheet, int $scopeStart): void
    {
        $this->sectionHeader($sheet, 4, 'INTRODUCCIÓN');
        $sheet->mergeCells('A5:L5');
        $sheet->setCellValue('A5', $this->narrative['purpose'].' Las cifras salen de los registros de UrbanBlade al momento de generar este archivo.');
        $sheet->getStyle('A5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension(5)->setRowHeight(44);

        $this->sectionHeader($sheet, $scopeStart - 1, 'ALCANCE');
        $entries = array_merge([['Periodo', $this->narrative['period']]], $this->narrative['scope']);
        foreach ($entries as $i => [$label, $value]) {
            $row = $scopeStart + $i;
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->mergeCells("D{$row}:L{$row}");
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('D'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->getColor()->setRGB('777777');
            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
        }
    }

    /** Cuatro tarjetas oscuras de tres columnas cada una: etiqueta, cifra grande y nota en dorado. */
    private function kpis(Worksheet $sheet, int $headerRow): void
    {
        $this->sectionHeader($sheet, $headerRow, 'INDICADORES CLAVE');
        $first = $headerRow + 1;
        $sheet->getRowDimension($first + 1)->setRowHeight(32);

        foreach (array_slice($this->narrative['kpis'], 0, 4) as $i => $kpi) {
            $from = chr(ord('A') + $i * 3);
            $to = chr(ord($from) + 2);
            foreach ([[$first, strtoupper($kpi['label']), 8, '999999', false], [$first + 1, $kpi['value'], 18, 'FFFFFF', true], [$first + 2, $kpi['hint'], 9, 'D4AF37', false]] as [$row, $text, $size, $color, $bold]) {
                $sheet->mergeCells("{$from}{$row}:{$to}{$row}");
                $sheet->setCellValue($from.$row, $text);
                $sheet->getStyle($from.$row)->getFont()->setSize($size)->setBold($bold)->getColor()->setRGB($color);
                $sheet->getStyle($from.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
            }
            $block = "{$from}{$first}:{$to}".($first + 2);
            $sheet->getStyle($block)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0A0A0A');
            $sheet->getStyle("{$from}{$first}:{$to}{$first}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB('D4AF37');
            // Separación visual entre tarjetas: borde blanco a la derecha de cada una.
            $sheet->getStyle($block)->getBorders()->getRight()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB('FFFFFF');
        }
    }

    private function findings(Worksheet $sheet, int $headerRow): void
    {
        $this->sectionHeader($sheet, $headerRow, 'HALLAZGOS PRINCIPALES');
        foreach ($this->narrative['findings'] as $i => $finding) {
            $row = $headerRow + 1 + $i;
            $sheet->mergeCells("B{$row}:L{$row}");
            $sheet->setCellValue('A'.$row, $i + 1);
            $sheet->setCellValue('B'.$row, $finding);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('D4AF37');
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:L{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
        }
    }

    /** Etiquetas y valores de la gráfica en N:O; las fechas ISO se muestran como dd/mm. */
    private function chartSource(Worksheet $sheet, int $chartHeader): void
    {
        if (! $this->hasChart()) {
            return;
        }

        $this->sectionHeader($sheet, $chartHeader, strtoupper($this->chart['title'] ?? 'GRÁFICA'));
        $sheet->setCellValue('N4', 'Etiqueta');
        $sheet->setCellValue('O4', 'Valor');
        $sheet->getStyle('N4:O4')->getFont()->setBold(true)->getColor()->setRGB('777777');
        foreach ($this->chart['labels'] as $i => $label) {
            $sheet->setCellValue('N'.($i + 5), $this->label((string) $label));
            $sheet->setCellValue('O'.($i + 5), (float) ($this->chart['values'][$i] ?? 0));
        }
    }

    private function label(string $label): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $label) ? Carbon::parse($label)->format('d/m') : $label;
    }

    public function charts(): array
    {
        if (! $this->hasChart()) {
            return [];
        }

        $count = count($this->chart['labels']);
        $last = $count + 4;
        $categoryRef = new DataSeriesValues('String', "'Resumen'!\$N\$5:\$N\${$last}", null, $count);
        $valueRef = new DataSeriesValues('Number', "'Resumen'!\$O\$5:\$O\${$last}", null, $count);
        // Una barra de color por categoría; en series de tiempo (muchos puntos) un solo dorado para no marear.
        $valueRef->setFillColor($count <= 8 ? array_slice(self::PALETTE, 0, $count) : 'D4AF37');

        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], [], [$categoryRef], [$valueRef]);
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart(
            'chart_resumen',
            new Title($this->chart['title'] ?? 'Gráfica'),
            null,
            new PlotArea(null, [$series]),
            true,
            DataSeries::EMPTY_AS_GAP,
            new Title($this->chart['x_label'] ?? 'Categoría'),
            new Title($this->chart['y_label'] ?? 'Valor'),
        );

        $top = $this->layout()['chart'] + 1;
        $chart->setTopLeftPosition('A'.$top);
        $chart->setBottomRightPosition('L'.($top + 19));
        $format = ($this->chart['unit'] ?? null) === 'MXN' ? '"$"#,##0' : '#,##0';
        $chart->getChartAxisY()->setAxisNumberProperties($format, false);

        return [$chart];
    }
}
