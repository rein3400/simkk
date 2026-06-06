<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(private readonly array $rows) {}

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return ['Produk', 'Stok', 'Batch', 'HPP (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['Produk'],
            $row['Stok'],
            $row['Batch'],
            number_format($row['HPP'], 0, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 10,
            'C' => 18,
            'D' => 16,
        ];
    }
}
