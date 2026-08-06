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
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericReportExport implements FromCollection, ShouldAutoSize, WithCharts, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array{title?: string, labels?: array, values?: array}|null  $chart
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
        private readonly string $reportTitle = 'Reporte BarberPro',
        private readonly ?array $chart = null,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

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
                if (empty($this->chart['labels']) || empty($this->chart['values'])) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
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
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new Title($this->chart['title'] ?? 'Gráfica');

        $chartObj = new Chart('chart_'.substr(md5($sheetTitle), 0, 8), $title, $legend, $plotArea);
        $chartObj->setTopLeftPosition('AD2');
        $chartObj->setBottomRightPosition('AN20');

        return [$chartObj];
    }
}
