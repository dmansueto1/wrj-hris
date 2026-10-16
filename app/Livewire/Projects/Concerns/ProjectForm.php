<?php

namespace App\Livewire\Projects\Concerns;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Validation\Rule;

/**
 * Fields and validation shared by the create and edit project screens (T-07).
 */
trait ProjectForm
{
    public string $code = '';

    public string $name = '';

    public string $location = '';

    public string $client_name = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $budget = '';

    public string $status = Project::STATUS_PLANNING;

    /**
     * Validate the form. The code is uppercased first so "abc-01" and "ABC-01" count as the same code.
     *
     * @return array{code: string, name: string, location: string, client_name: ?string, start_date: ?string, end_date: ?string, budget: ?string, status: string}
     */
    protected function validateProject(?int $ignoreProjectId = null): array
    {
        $this->code = ProjectService::normalizeCode($this->code);

        /** @var array{code: string, name: string, location: string, client_name: ?string, start_date: ?string, end_date: ?string, budget: ?string, status: string} $validated */
        $validated = $this->validate([
            // Archived projects keep their code, so the unique check includes them.
            'code' => ['required', 'string', 'max:30', Rule::unique(Project::class)->ignore($ignoreProjectId)],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', Rule::when($this->start_date !== '', 'after_or_equal:start_date')],
            // 0 or more, at most two decimals, fits DECIMAL(14,2).
            'budget' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
        ], [
            'end_date.after_or_equal' => __('The end date must be on or after the start date.'),
            'budget.regex' => __('The budget must be 0 or more, with at most two decimals.'),
        ]);

        return $validated;
    }

    protected function fillFromProject(Project $project): void
    {
        $this->code = $project->code;
        $this->name = $project->name;
        $this->location = $project->location;
        $this->client_name = $project->client_name ?? '';
        $this->start_date = $project->start_date?->toDateString() ?? '';
        $this->end_date = $project->end_date?->toDateString() ?? '';
        $this->budget = $project->budget ?? '';
        $this->status = $project->status;
    }
}
