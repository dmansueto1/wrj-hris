<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Project;
use App\Services\ProjectExpenseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Project page (T-16, PRD US-31): labor total, materials total and running expenses, the budget
 * used, and four breakdowns in tabs. The From / To filter applies to the totals and every tab; it
 * starts at the project's start date until today, or empty when the project has no start date.
 * Admin and Staff (expenses.view).
 *
 * @property-read array{labor: string, materials: string, running: string} $totals
 * @property-read array{labor: string, materials: string} $shares
 * @property-read array{percent: string, width: int, level: string}|null $budget
 * @property-read LengthAwarePaginator<int, array<string, mixed>> $rows
 */
class Show extends Component
{
    use WithPerPage;

    /** Tab => [label, icon]. */
    public const TABS = [
        'labor-worker' => ['Labor by worker', 'users'],
        'labor-date' => ['Labor by date', 'calendar-days'],
        'materials-item' => ['Materials by item', 'cube'],
        'materials-date' => ['Materials by date', 'shopping-cart'],
    ];

    public Project $project;

    #[Url(except: 'labor-worker')]
    public string $tab = 'labor-worker';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('expenses.view'), 403);
    }

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->tab = $this->validTab($this->tab);
        $this->normalizeRange();

        // No dates in the URL: the project's start date until today (none without a start date).
        if ($this->from === '' && $this->to === '') {
            $this->applyDefaultRange();
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->tab = $this->validTab($this->tab);
        }

        if (in_array($property, ['from', 'to'], true)) {
            $this->normalizeRange();
        }

        if (in_array($property, ['tab', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Back to the default range: the project's start date until today.
     */
    public function clearFilters(): void
    {
        $this->applyDefaultRange();
        $this->resetPage();
    }

    /**
     * @return array{labor: string, materials: string, running: string}
     */
    #[Computed]
    public function totals(): array
    {
        return app(ProjectExpenseService::class)->totals($this->project, $this->rangeFrom(), $this->rangeTo());
    }

    /**
     * Labor's and materials' share of running expenses, e.g. ['labor' => '9.1', 'materials' => '90.9'].
     *
     * @return array{labor: string, materials: string}
     */
    #[Computed]
    public function shares(): array
    {
        return app(ProjectExpenseService::class)->shares($this->totals);
    }

    /**
     * @return array{percent: string, width: int, level: string}|null
     */
    #[Computed]
    public function budget(): ?array
    {
        return app(ProjectExpenseService::class)->budgetUsage($this->totals['running'], $this->project->budget);
    }

    /**
     * The rows of the open tab.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $expenses = app(ProjectExpenseService::class);
        $args = [$this->project, $this->rangeFrom(), $this->rangeTo(), $this->perPage()];

        return match ($this->tab) {
            'labor-date' => $expenses->laborByDate(...$args),
            'materials-item' => $expenses->materialsByItem(...$args),
            'materials-date' => $expenses->materialsByDate(...$args),
            default => $expenses->laborByWorker(...$args),
        };
    }

    public function render(): View
    {
        return view('livewire.projects.show', [
            'tabs' => self::TABS,
            // Chips and "Clear filters" only when the range differs from the default.
            'hasFilters' => [$this->from, $this->to] !== $this->defaultRange(),
            'canManage' => (bool) Auth::user()?->can('projects.manage'),
        ])->title($this->project->name);
    }

    private function applyDefaultRange(): void
    {
        [$this->from, $this->to] = $this->defaultRange();
    }

    /**
     * @return array{string, string}
     */
    private function defaultRange(): array
    {
        $range = app(ProjectExpenseService::class)->defaultRange($this->project);

        return $range === null ? ['', ''] : [$range['from'], $range['to']];
    }

    private function validTab(string $tab): string
    {
        return array_key_exists($tab, self::TABS) ? $tab : 'labor-worker';
    }

    /**
     * Invalid dates dropped, From and To swapped when From is later (ProjectExpenseService::range()).
     */
    private function normalizeRange(): void
    {
        [$this->from, $this->to] = app(ProjectExpenseService::class)->range($this->from, $this->to);
    }

    private function rangeFrom(): ?string
    {
        return $this->from === '' ? null : $this->from;
    }

    private function rangeTo(): ?string
    {
        return $this->to === '' ? null : $this->to;
    }
}
