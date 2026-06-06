<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommissionReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(private readonly array $rows) {}

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return ['ID Pegawai', 'Nama Terapis', 'Jumlah Tindakan', 'Total Komisi (Rp)', 'Gaji Pokok (Rp)', 'Grand Total (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['ID Pegawai'],
            $row['Nama Terapis'],
            $row['Jumlah Tindakan'],
            number_format($row['Total Komisi'], 0, ',', '.'),
            number_format($row['Gaji Pokok'], 0, ',', '.'),
            number_format($row['Grand Total'], 0, ',', '.'),
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
            'A' => 14,
            'B' => 22,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
        ];
    }
}
