<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceHistoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * All Attendance (T-11): everyone's attendance with labor cost and clock-in/out photos, for
 * Admin and Staff. Totals cover every filtered record, not just the current page.
 *
 * @property-read LengthAwarePaginator<int, Attendance> $attendances
 * @property-read array{hours: string, labor_cost: string} $totals
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Project> $projects
 */
#[Title('All Attendance')]
class Index extends Component
{
    use WithPerPage;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.view-all'), 403);
    }

    public function mount(AttendanceHistoryService $history): void
    {
        [$this->from, $this->to] = $history->range($this->from, $this->to);
    }

    public function updated(string $property): void
    {
        $history = app(AttendanceHistoryService::class);

        if (in_array($property, ['from', 'to'], true)) {
            [$this->from, $this->to] = $history->range($this->from, $this->to);
        }

        if (in_array($property, ['from', 'to', 'user', 'project', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearDates(AttendanceHistoryService $history): void
    {
        [$this->from, $this->to] = $history->currentWeek();
        $this->resetPage();
    }

    /**
     * Back to this week, everyone, every project and every status.
     */
    public function clearFilters(AttendanceHistoryService $history): void
    {
        $this->reset('user', 'project', 'status');
        [$this->from, $this->to] = $history->currentWeek();
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Attendance>
     */
    #[Computed]
    public function attendances(): LengthAwarePaginator
    {
        return app(AttendanceHistoryService::class)->all($this->filters(), $this->perPage());
    }

    /**
     * @return array{hours: string, labor_cost: string}
     */
    #[Computed]
    public function totals(): array
    {
        return app(AttendanceHistoryService::class)->totals($this->filters());
    }

    /**
     * Everyone who can have attendance, archived users included so old records stay findable.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::withTrashed()->orderBy('name')->get(['id', 'name', 'employee_no']);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::withTrashed()->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function render(): View
    {
        $history = app(AttendanceHistoryService::class);

        return view('livewire.attendance.index', [
            'clockedInNow' => $history->clockedInNow(),
            'isThisWeek' => [$this->from, $this->to] === $history->currentWeek(),
            'hasFilters' => $this->user !== '' || $this->project !== '' || $this->status !== '',
            'statuses' => Attendance::STATUSES,
        ]);
    }

    /**
     * @return array{from: string, to: string, user: ?int, project: ?int, status: ?string}
     */
    private function filters(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'user' => ctype_digit($this->user) ? (int) $this->user : null,
            'project' => ctype_digit($this->project) ? (int) $this->project : null,
            'status' => $this->status !== '' ? $this->status : null,
        ];
    }
}
