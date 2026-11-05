<?php

namespace App\Exports\AttendanceImportTemplate;

use App\Services\AttendanceImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * How to fill in the Attendance sheet, one rule per line, in simple English.
 */
class InstructionsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Instructions';
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['How to fill in the Attendance sheet'],
            ['One row per clock-in and clock-out. A morning and an afternoon are two rows.'],
            ['Employee No.: copy it from the Employees sheet.'],
            ['Employee Name: only for reading. The system does not check it.'],
            ['Project Code: copy it from the Projects sheet. Only Ongoing projects can be used.'],
            ['Date: the day of the clock-in, written as 2026-10-21.'],
            ['Clock In and Clock Out: written as 7:00 AM or 07:00 (24-hour).'],
            ['A Clock Out earlier than the Clock In means the next day (overnight), e.g. 10:00 PM to 6:00 AM.'],
            ['Rows with '.AttendanceImportService::EXAMPLE_MARKER.' in Employee Name are examples and are skipped.'],
            ['At most '.number_format(AttendanceImportService::MAX_ROWS).' rows per file.'],
            ['On a Mac with Numbers: choose File → Export To → Excel and upload the exported .xlsx file (not the Numbers file).'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true, 'size' => 13]]];
    }
}
