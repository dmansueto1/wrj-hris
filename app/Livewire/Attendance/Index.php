<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceHistoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * All Attendance (T-11, T-11.4), for Admin and Staff, in tabs:
 * - Daily attendance: every employee on one date, sorted by last name, Present or Absent.
 * - By employee: one employee's attendance day by day over a date range, Absent days included.
 * - Import Attendance (T-12.1, Admin only): the Attendance\Import component.
 *
 * @property-read LengthAwarePaginator<int, array{user: User, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}> $daily
 * @property-read array{employees: int, present: int, absent: int, labor_cost: string} $dailySummary
 * @property-read LengthAwarePaginator<int, array{date: Carbon, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}>|null $employeeDays
 * @property-read array{present_days: int, absent_days: int, hours: string, labor_cost: string}|null $employeeSummary
 * @property-read array{from: string, to: string, shortened: bool} $range
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Project> $projects
 * @property-read string|null $projectName
 */
#[Title('All Attendance')]
class Index extends Component
{
    use WithPerPage;

    public const TABS = ['daily', 'employee', 'import'];

    #[Url(as: 'tab', except: 'daily')]
    public string $tab = 'daily';

    /**
     * Daily attendance: the date (today by default), the employee and project filters ('' = all)
     * and the sort order (AttendanceHistoryService::DAILY_SORTS).
     */
    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $project = '';

    #[Url(as: 'sort', except: 'name')]
    public string $sortBy = 'name';

    /** By employee: the chosen employee ('' = none yet) and the date range (this week by default). */
    #[Url(except: '')]
    public string $employee = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.view-all'), 403);
    }

    public function mount(AttendanceHistoryService $history): void
    {
        $this->tab = $this->validTab($this->tab);
        $this->date = $history->date($this->date);
        $this->sortBy = $this->validSort($this->sortBy);
        [$this->from, $this->to] = $history->range($this->from, $this->to);
    }

    public function updated(string $property): void
    {
        $history = app(AttendanceHistoryService::class);

        match ($property) {
            'tab' => $this->tab = $this->validTab($this->tab),
            'date' => $this->date = $history->date($this->date),
            'sortBy' => $this->sortBy = $this->validSort($this->sortBy),
            'from', 'to' => [$this->from, $this->to] = $history->range($this->from, $this->to),
            default => null,
        };

        if (in_array($property, ['tab', 'date', 'user', 'project', 'sortBy', 'employee', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Back to today, everyone, every project and Name order on Daily attendance; to no employee and
     * this week on By employee.
     */
    public function clearFilters(AttendanceHistoryService $history): void
    {
        if ($this->tab === 'employee') {
            $this->employee = '';
            [$this->from, $this->to] = $history->currentWeek();
        } else {
            $this->reset('user', 'project', 'sortBy');
            $this->date = $history->date('');
        }

        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{user: User, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}>
     */
    #[Computed]
    public function daily(): LengthAwarePaginator
    {
        return app(AttendanceHistoryService::class)->daily($this->date, $this->selectedId($this->user), $this->selectedId($this->project), $this->sortBy, $this->perPage());
    }

    /**
     * @return array{employees: int, present: int, absent: int, labor_cost: string}
     */
    #[Computed]
    public function dailySummary(): array
    {
        return app(AttendanceHistoryService::class)->dailySummary($this->date, $this->selectedId($this->user), $this->selectedId($this->project));
    }

    /**
     * @return LengthAwarePaginator<int, array{date: Carbon, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}>|null
     */
    #[Computed]
    public function employeeDays(): ?LengthAwarePaginator
    {
        $employee = $this->selectedId($this->employee);

        return $employee === null ? null : app(AttendanceHistoryService::class)->employeeDays($employee, $this->from, $this->to, $this->perPage());
    }

    /**
     * @return array{present_days: int, absent_days: int, hours: string, labor_cost: string}|null
     */
    #[Computed]
    public function employeeSummary(): ?array
    {
        $employee = $this->selectedId($this->employee);

        return $employee === null ? null : app(AttendanceHistoryService::class)->employeeSummary($employee, $this->from, $this->to);
    }

    /**
     * @return array{from: string, to: string, shortened: bool}
     */
    #[Computed]
    public function range(): array
    {
        return app(AttendanceHistoryService::class)->cappedRange($this->from, $this->to);
    }

    /**
     * Employees for both dropdowns, "Last, First (EMP-0042)" sorted by last name. Inactive and
     * archived employees are included so their history can still be looked up.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::withTrashed()
            ->permission('attendance.clock')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_no']);
    }

    /**
     * Ongoing projects for the Daily attendance project filter, A–Z.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->ongoing()->orderBy('name')->get(['id', 'code', 'name']);
    }

    /**
     * The filtered project's name for its chip, even when it is no longer Ongoing.
     */
    #[Computed]
    public function projectName(): ?string
    {
        return ctype_digit($this->project) ? Project::withTrashed()->find((int) $this->project)?->name : null;
    }

    /**
     * Re-render with the corrected figures after the correction modal saves (T-12).
     */
    #[On('attendance-corrected')]
    public function refreshAfterCorrection(): void
    {
        unset($this->daily, $this->dailySummary, $this->employeeDays, $this->employeeSummary);
    }

    public function render(): View
    {
        return view('livewire.attendance.index', [
            'today' => now()->toDateString(),
            'thisWeek' => app(AttendanceHistoryService::class)->currentWeek(),
        ]);
    }

    /**
     * The tab if it exists and the user may see it (Import Attendance is Admin only), otherwise Daily attendance.
     */
    private function validTab(string $tab): string
    {
        $allowed = in_array($tab, self::TABS, true) && ($tab !== 'import' || Auth::user()?->can('attendance.correct'));

        return $allowed ? $tab : 'daily';
    }

    private function validSort(string $sort): string
    {
        return in_array($sort, AttendanceHistoryService::DAILY_SORTS, true) ? $sort : 'name';
    }

    private function selectedId(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }
}
