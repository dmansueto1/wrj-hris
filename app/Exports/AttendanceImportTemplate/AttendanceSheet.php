<?php

namespace App\Exports\AttendanceImportTemplate;

use App\Services\AttendanceImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The sheet to fill: the template columns and two example rows the import skips.
 */
class AttendanceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Attendance';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return AttendanceImportService::HEADINGS;
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $example = 'Juan Dela Cruz '.AttendanceImportService::EXAMPLE_MARKER;

        return [
            ['EMP-00001', $example, 'PRJ-0001', '2026-10-19', '7:00 AM', '11:30 AM'],
            ['EMP-00001', $example, 'PRJ-0001', '2026-10-19', '12:30 PM', '5:00 PM'],
        ];
    }

    /**
     * Bold headings, gray italic examples, and every column as text so Excel and Numbers keep what
     * is typed ("001" stays 001, "7:00 AM" stays a time as written).
     *
     * @return array<string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = AttendanceImportService::MAX_ROWS + 1;

        return [
            '1' => ['font' => ['bold' => true]],
            'A2:F3' => ['font' => ['italic' => true, 'color' => ['rgb' => '808080']]],
            "A2:F{$lastRow}" => ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_TEXT]],
        ];
    }
}
