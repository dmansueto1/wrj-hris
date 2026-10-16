<?php

namespace App\Livewire\Projects;

use App\Livewire\Projects\Concerns\ProjectForm;
use App\Models\Project;
use App\Services\ProjectService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit project')]
class Edit extends Component
{
    use ProjectForm;

    public Project $project;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('projects.manage'), 403);
    }

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->fillFromProject($project);
    }

    public function save(ProjectService $projects): void
    {
        $projects->update($this->project, $this->validateProject($this->project->id));

        Flux::toast(variant: 'success', text: __('Project :code updated.', ['code' => $this->project->code]));

        $this->redirectRoute('projects.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.projects.edit', [
            'statuses' => Project::STATUSES,
        ]);
    }
}
