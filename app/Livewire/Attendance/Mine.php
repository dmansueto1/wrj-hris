<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Attendance;
use App\Services\AttendanceHistoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * My Attendance (T-11): the signed-in user's own attendance grouped by day, with hours only.
 * No peso amounts and no photos, whatever the role.
 *
 * @property-read LengthAwarePaginator<int, array{date: Carbon, attendances: EloquentCollection<int, Attendance>}> $days
 * @property-read array{hours: string, count: int} $totals
 */
#[Title('My Attendance')]
class Mine extends Component
{
    use WithPerPage;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.view-own'), 403);
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
            $this->resetPage();
        }
    }

    public function thisWeek(AttendanceHistoryService $history): void
    {
        [$this->from, $this->to] = $history->currentWeek();
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{date: Carbon, attendances: EloquentCollection<int, Attendance>}>
     */
    #[Computed]
    public function days(): LengthAwarePaginator
    {
        return app(AttendanceHistoryService::class)->ownDays(Auth::user(), $this->from, $this->to, $this->perPage());
    }

    /**
     * @return array{hours: string, count: int}
     */
    #[Computed]
    public function totals(): array
    {
        return app(AttendanceHistoryService::class)->ownTotals(Auth::user(), $this->from, $this->to);
    }

    public function render(): View
    {
        $history = app(AttendanceHistoryService::class);

        return view('livewire.attendance.mine', [
            'isThisWeek' => [$this->from, $this->to] === $history->currentWeek(),
        ]);
    }
}
