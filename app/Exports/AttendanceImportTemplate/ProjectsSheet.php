<?php

namespace App\Exports\AttendanceImportTemplate;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Every Ongoing project, by code: the only projects an imported attendance can be on.
 */
class ProjectsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Projects';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Code', 'Name', 'Location'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return Project::query()
            ->ongoing()
            ->orderBy('code')
            ->get(['code', 'name', 'location'])
            ->map(fn (Project $project) => [$project->code, $project->name, (string) $project->location])
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
