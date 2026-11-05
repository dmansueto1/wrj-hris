<?php

namespace App\Exports\AttendanceImportTemplate;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Every active employee who clocks in, by last name, to look up the employee no.
 */
class EmployeesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Employees';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Employee No.', 'Name', 'Shift'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return User::query()
            ->permission('attendance.clock')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['employee_no', 'first_name', 'last_name', 'shift_type'])
            ->map(fn (User $user) => [$user->employee_no, $user->listName(), User::SHIFT_TYPES[$user->shift_type] ?? ''])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
