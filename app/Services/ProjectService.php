<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Str;

class ProjectService
{
    /**
     * @param  array{code: string, name: string, location: string, client_name: ?string, start_date: ?string, end_date: ?string, budget: ?string, status: string}  $data
     */
    public function create(array $data): Project
    {
        $project = new Project;
        $this->fill($project, $data)->save();

        return $project;
    }

    /**
     * @param  array{code: string, name: string, location: string, client_name: ?string, start_date: ?string, end_date: ?string, budget: ?string, status: string}  $data
     */
    public function update(Project $project, array $data): void
    {
        $this->fill($project, $data)->save();
    }

    /**
     * Counts for the stat cards above the projects list (archived projects excluded).
     *
     * @return array{total: int, statuses: array<string, int>}
     */
    public function summary(): array
    {
        $byStatus = Project::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $statuses = [];
        foreach (array_keys(Project::STATUSES) as $status) {
            $statuses[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return ['total' => array_sum($statuses), 'statuses' => $statuses];
    }

    /**
     * Archive (soft-delete) the project. Its attendance and material records stay intact.
     */
    public function archive(Project $project): void
    {
        $project->delete();
    }

    /**
     * Codes are stored in uppercase ("abc-01" becomes "ABC-01").
     */
    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * @param  array{code: string, name: string, location: string, client_name: ?string, start_date: ?string, end_date: ?string, budget: ?string, status: string}  $data
     */
    private function fill(Project $project, array $data): Project
    {
        return $project->forceFill([
            'code' => self::normalizeCode($data['code']),
            'name' => $data['name'],
            'location' => $data['location'],
            'client_name' => $data['client_name'] ?: null,
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'budget' => ($data['budget'] ?? '') === '' ? null : $data['budget'],
            'status' => $data['status'],
        ]);
    }
}
