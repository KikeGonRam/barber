<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportador de Excel generico y reutilizable para cualquier tipo de
 * reporte (ingresos, citas, inventario, clientes, etc.). Las filas y
 * encabezados ya vienen agregados por ReportService::reportByType(); esta
 * clase solo se encarga del formato del archivo (estilos, titulo, grafico
 * de barras opcional). Se instancia desde Report\ReportController::export()
 * y desde Api\ReportController.
 */
class GenericReportExport implements FromCollection, ShouldAutoSize, WithCharts, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array{title?: string, labels?: array, values?: array, unit?: string, x_label?: string, y_label?: string}|null  $chart
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
        private readonly string $reportTitle = 'Reporte BarberPro',
        private readonly ?array $chart = null,
        private readonly array $keys = [],
    ) {}

    // Columnas en pesos (formato moneda) y columnas que además llevan renglón de total al final.
    private const MONEY_KEYS = ['monto', 'propina', 'total', 'precio_cobrado', 'precio_venta', 'gasto_total'];

    private const SUMMED_KEYS = ['monto', 'propina', 'total', 'precio_cobrado', 'gasto_total'];

    private const COUNT_KEYS = ['visitas', 'completadas', 'stock_actual', 'stock_minimo', 'movimientos'];

    /**
     * Estilo de la hoja de detalle: panel congelado, filtros, rayado, formato de moneda y renglón de totales.
     */
    private function styleDetail(Worksheet $sheet): void
    {
        $lastRow = $this->rows->count() + 1;
        $lastColumn = Coordinate::stringFromColumnIndex(max(count($this->headings), 1));

        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setRGB('0A0A0A');
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        if ($lastRow > 1) {
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CCCCCC');
            $zebra = new Conditional;
            $zebra->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=0');
            $zebra->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('F7F7F5');
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->setConditionalStyles([$zebra]);
        }

        foreach ($this->keys as $i => $key) {
            $column = Coordinate::stringFromColumnIndex($i + 1);
            $isMoney = in_array($key, self::MONEY_KEYS, true);
            if ($isMoney || in_array($key, self::COUNT_KEYS, true)) {
                $range = "{$column}2:{$column}".($lastRow + 1);
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode($isMoney ? '"$"#,##0.00' : '#,##0');
                $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $this->addTotalsRow($sheet, $lastRow, $lastColumn);
    }

    private function addTotalsRow(Worksheet $sheet, int $lastRow, string $lastColumn): void
    {
        $sums = array_filter($this->keys, fn ($key) => in_array($key, self::SUMMED_KEYS, true));
        if ($lastRow < 2 || $sums === []) {
            return;
        }

        $totalRow = $lastRow + 1;
        $sheet->setCellValue('A'.$totalRow, 'TOTAL');
        foreach (array_keys($sums) as $i) {
            $column = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($column.$totalRow, "=SUBTOTAL(109,{$column}2:{$column}{$lastRow})");
        }
        $style = $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}");
        $style->getFont()->setBold(true)->getColor()->setRGB('D4AF37');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0A0A0A');
    }

    /**
     * Filas de datos ya calculadas por el servicio de reportes.
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * Encabezados de columna, definidos por el tipo de reporte solicitado.
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * Estilo de la fila de encabezado con los colores de marca
     * (dorado sobre negro).
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as header
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'D4AF37'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0A0A0A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Nombre de la pestana del Excel.
     */
    public function title(): string
    {
        return $this->reportTitle;
    }

    /**
     * Escribe los datos fuente del grafico en columnas apartadas (AA/AB) para
     * no interferir con la tabla visible. Se ejecuta antes de que el writer
     * guarde el archivo, asi que el grafico (ver charts()) ya encuentra los
     * valores en su lugar.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->keys !== []) {
                    $this->styleDetail($sheet);
                }

                if (empty($this->chart['labels']) || empty($this->chart['values'])) {
                    return;
                }

                $labels = $this->chart['labels'];
                $values = $this->chart['values'];

                $sheet->setCellValue('AA1', 'Etiqueta');
                $sheet->setCellValue('AB1', 'Valor');
                foreach ($labels as $i => $label) {
                    $row = $i + 2;
                    $sheet->setCellValue('AA'.$row, (string) $label);
                    $sheet->setCellValue('AB'.$row, (float) ($values[$i] ?? 0));
                }
            },
        ];
    }

    /**
     * Grafico de barras nativo de Excel (embebido, editable), construido a
     * partir de los datos ya agregados por ReportService. Los valores fuente
     * se escriben en las columnas AA/AB (ver registerEvents()) — asume que
     * ningun reporte llega a tener 27+ columnas (los 4 tipos actuales
     * tienen 4-8); si se agrega un reporte con muchas mas columnas, mover
     * AA/AB mas a la derecha.
     */
    public function charts(): array
    {
        $labels = $this->chart['labels'] ?? [];
        $values = $this->chart['values'] ?? [];

        if (empty($labels) || empty($values)) {
            return [];
        }

        $count = count($labels);
        $lastRow = $count + 1;
        $sheetTitle = $this->title();

        $categories = [new DataSeriesValues('String', "'{$sheetTitle}'!\$AA\$2:\$AA\${$lastRow}", null, $count)];
        $dataValues = [new DataSeriesValues('Number', "'{$sheetTitle}'!\$AB\$2:\$AB\${$lastRow}", null, $count)];
        // Dorado de marca en vez del azul por defecto de Excel, para que el
        // grafico nativo combine con el resto de exportes (PDF/dashboard).
        $dataValues[0]->setFillColor('D4AF37');

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataValues) - 1),
            [],
            $categories,
            $dataValues
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        // Una sola serie no necesita leyenda: el titulo ya dice que se grafica
        // y la leyenda solo repetia "Valor" ocupando espacio.
        $title = new Title($this->chart['title'] ?? 'Gráfica');

        // Encabezados de eje: sin esto Excel solo muestra numeros/etiquetas
        // sueltas sin decir a que se refieren.
        $xAxisLabel = new Title($this->chart['x_label'] ?? 'Categoría');
        $yAxisLabel = new Title($this->chart['y_label'] ?? 'Valor');

        $chartObj = new Chart(
            'chart_'.substr(md5($sheetTitle), 0, 8),
            $title,
            null,
            $plotArea,
            true,
            DataSeries::EMPTY_AS_GAP,
            $xAxisLabel,
            $yAxisLabel
        );
        $chartObj->setTopLeftPosition('AD2');
        $chartObj->setBottomRightPosition('AO22');

        // Formato de moneda explicito en el eje de valores: sin esto Excel
        // abrevia automaticamente montos grandes como "12K" en vez de mostrar
        // el peso completo — importante porque estos valores son pesos
        // mexicanos, no una cifra generica.
        $yAxis = $chartObj->getChartAxisY();
        if ($yAxis !== null) {
            $format = ($this->chart['unit'] ?? null) === 'MXN' ? '"$"#,##0' : '#,##0';
            $yAxis->setAxisNumberProperties($format, false);
        }

        return [$chartObj];
    }
}
