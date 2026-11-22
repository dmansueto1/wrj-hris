<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Project;
use App\Services\ProjectExpenseService;
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

    /**
     * Budget used (T-16): '' every project, "watch" 80% or more, "over" 100% or more. The Dashboard's
     * "Projects near budget" card links here with "watch".
     */
    #[Url(except: '')]
    public string $budget = '';

    /** Budget filter => [label, minimum percent used]. */
    public const BUDGET_FILTERS = [
        'watch' => ['80% or more used', ProjectExpenseService::WARNING_PERCENT],
        'over' => ['Over budget', 100],
    ];

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
        if (in_array($property, ['search', 'status', 'budget'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'budget');
        $this->resetPage();
    }

    public function archive(int $projectId, ProjectService $projects): void
    {
        abort_unless($this->canManage(), 403);

        $projects->archive(Project::findOrFail($projectId));

        unset($this->projects);
        Flux::toast(text: __('Project archived.'));
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        $search = trim($this->search);

        $expenses = app(ProjectExpenseService::class);
        $minimum = self::BUDGET_FILTERS[$this->budget][1] ?? null;

        // T-16: labor and materials sums per project in two subqueries, for the Running expenses column.
        return $expenses->withTotals(Project::query())
            ->when($minimum !== null, fn ($query) => $expenses->whereBudgetUsedAtLeast($query, $minimum))
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
            'budgetFilters' => self::BUDGET_FILTERS,
            'hasFilters' => trim($this->search) !== '' || $this->status !== '' || isset(self::BUDGET_FILTERS[$this->budget]),
            'view' => $this->view === 'table' ? 'table' : 'grid',
            'expenses' => app(ProjectExpenseService::class),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) Auth::user()?->can('projects.manage');
    }
}
