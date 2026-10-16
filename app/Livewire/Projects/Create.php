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

#[Title('Add project')]
class Create extends Component
{
    use ProjectForm;

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('projects.manage'), 403);
    }

    public function save(ProjectService $projects): void
    {
        $project = $projects->create($this->validateProject());

        Flux::toast(variant: 'success', text: __('Project :code created.', ['code' => $project->code]));

        $this->redirectRoute('projects.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.projects.create', [
            'statuses' => Project::STATUSES,
        ]);
    }
}
