<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Project;
use App\Services\ProjectService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Projects')]
class Index extends Component
{
    use WithPerPage;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** "grid" (cards) or "table". */
    #[Url(except: 'grid')]
    public string $view = 'grid';

    /**
     * Runs on every request. Admin (projects.manage) and Staff (expenses.view) may see the list.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->canAny(['expenses.view', 'projects.manage']), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function archive(int $projectId, ProjectService $projects): void
    {
        abort_unless($this->canManage(), 403);

        $projects->archive(Project::findOrFail($projectId));

        unset($this->projects, $this->summary);
        Flux::toast(text: __('Project archived.'));
    }

    /**
     * Counts for the stat cards.
     *
     * @return array{total: int, statuses: array<string, int>}
     */
    #[Computed]
    public function summary(): array
    {
        return app(ProjectService::class)->summary();
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Project::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($this->status, Project::STATUSES), fn ($query) => $query->where('status', $this->status))
            ->orderBy('code')
            ->paginate($this->perPage());
    }

    public function render(): View
    {
        return view('livewire.projects.index', [
            'statuses' => Project::STATUSES,
            'canManage' => $this->canManage(),
            'hasFilters' => trim($this->search) !== '' || $this->status !== '',
            'view' => $this->view === 'table' ? 'table' : 'grid',
        ]);
    }

    private function canManage(): bool
    {
        return (bool) Auth::user()?->can('projects.manage');
    }
}
