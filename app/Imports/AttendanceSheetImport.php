<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads one sheet of an uploaded attendance file (T-12.1), keyed by the slugged headings
 * ("Employee No." → employee_no). Blank rows are kept so row numbers match the spreadsheet. Only
 * reads; AttendanceImportService checks and saves.
 *
 * The sheet is picked by name ("Attendance"), because Numbers' "Export To → Excel" puts an
 * "Export Summary" sheet first. $found is false when the workbook has no such sheet (a CSV, or a
 * file of the Admin's own); the service then reads the first sheet instead.
 */
class AttendanceSheetImport implements SkipsUnknownSheets, ToCollection, WithHeadingRow, WithMultipleSheets
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public bool $found = true;

    /**
     * @param  string|int  $sheet  the sheet name, or its position (0 = first)
     */
    public function __construct(private string|int $sheet = 'Attendance')
    {
        $this->rows = new Collection;
    }

    /**
     * @return array<string|int, self>
     */
    public function sheets(): array
    {
        return [$this->sheet => $this];
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        $this->found = false;
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
