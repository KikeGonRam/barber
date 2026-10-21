<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de portada del libro: fondo negro con acentos dorados, título, periodo y datos de control.
 * Se pinta toda desde el evento AfterSheet porque no lleva una tabla de datos.
 */
class ReportCoverSheet implements WithEvents, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly string $folio,
        private readonly string $period,
    ) {}

    public function title(): string
    {
        return 'Portada';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setShowGridlines(false);
                $sheet->getTabColor()->setRGB('D4AF37');
                foreach (range('A', 'L') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(11);
                }

                // Fondo negro de toda la portada y franja dorada arriba.
                $sheet->getStyle('A1:L42')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0A0A0A');
                $sheet->getStyle('A1:L1')->getFill()->getStartColor()->setRGB('D4AF37');
                $sheet->getRowDimension(1)->setRowHeight(8);

                $this->line($sheet, 'B5', 'UrbanBlade', 30, 'FFFFFF', true);
                $this->line($sheet, 'B6', 'ELITE GROOMING STUDIO', 9, '8A8A8A');
                $this->line($sheet, 'B15', 'REPORTE EJECUTIVO', 11, 'D4AF37', true);
                $this->line($sheet, 'B17', $this->title, 34, 'FFFFFF', true);
                $sheet->getRowDimension(17)->setRowHeight(48);
                $sheet->getStyle('B19:D19')->getFill()->getStartColor()->setRGB('D4AF37');
                $sheet->getRowDimension(19)->setRowHeight(4);
                $this->line($sheet, 'B21', $this->period, 15, 'CFCFCF');

                // Los textos largos se funden en una celda ancha para que nunca se partan en varias líneas.
                foreach (['B5', 'B6', 'B15', 'B17', 'B21', 'B39'] as $cell) {
                    $row = substr($cell, 1);
                    $sheet->mergeCells("B{$row}:K{$row}");
                }

                $this->meta($sheet, 'B', 'GENERADO', now()->translatedFormat('d \d\e F \d\e Y'));
                $this->meta($sheet, 'F', 'FOLIO DEL REPORTE', $this->folio);
                $this->meta($sheet, 'I', 'PREPARADO POR', 'Administración UrbanBlade');
                $this->line($sheet, 'B39', 'DOCUMENTO CONFIDENCIAL · USO INTERNO · INTELIGENCIA DE NEGOCIO', 8, '777777');
            },
        ];
    }

    private function line(Worksheet $sheet, string $cell, string $text, int $size, string $color, bool $bold = false): void
    {
        $sheet->setCellValue($cell, $text);
        $sheet->getStyle($cell)->getFont()->setName('Calibri')->setSize($size)->setBold($bold)->getColor()->setRGB($color);
        $sheet->getStyle($cell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** Dato de control en dos filas (34 y 35): etiqueta pequeña arriba, valor debajo. */
    private function meta(Worksheet $sheet, string $column, string $label, string $value): void
    {
        $this->line($sheet, $column.'34', $label, 8, '777777', true);
        $this->line($sheet, $column.'35', $value, 12, 'FFFFFF', true);
    }
}
