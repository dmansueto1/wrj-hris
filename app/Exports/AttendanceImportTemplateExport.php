<?php

namespace App\Exports;

use App\Exports\AttendanceImportTemplate\AttendanceSheet;
use App\Exports\AttendanceImportTemplate\EmployeesSheet;
use App\Exports\AttendanceImportTemplate\InstructionsSheet;
use App\Exports\AttendanceImportTemplate\ProjectsSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The template Admins fill in when the system is down (T-12.1): the Attendance sheet to fill,
 * Employees and Projects to look up numbers and codes, and Instructions.
 */
class AttendanceImportTemplateExport implements Export, WithMultipleSheets
{
    public const FILE_NAME = 'attendance-import-template.xlsx';

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [new AttendanceSheet, new EmployeesSheet, new ProjectsSheet, new InstructionsSheet];
    }
}
