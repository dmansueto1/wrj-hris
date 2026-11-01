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
 * My Attendance (T-11, T-11.4): every day of the range for the signed-in user, Present or Absent
 * like All Attendance's By employee tab, with hours only. No peso amounts and no photos, whatever
 * the role.
 *
 * @property-read LengthAwarePaginator<int, array{date: Carbon, present: bool, attendances: EloquentCollection<int, Attendance>}> $days
 * @property-read array{present_days: int, absent_days: int, hours: string, labor_cost: string} $totals
 * @property-read array{from: string, to: string, shortened: bool} $range
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
     * @return LengthAwarePaginator<int, array{date: Carbon, present: bool, attendances: EloquentCollection<int, Attendance>}>
     */
    #[Computed]
    public function days(): LengthAwarePaginator
    {
        return app(AttendanceHistoryService::class)->employeeDays((int) Auth::id(), $this->from, $this->to, $this->perPage());
    }

    /**
     * Present and absent days and hours; the labor cost is computed but never shown here.
     *
     * @return array{present_days: int, absent_days: int, hours: string, labor_cost: string}
     */
    #[Computed]
    public function totals(): array
    {
        return app(AttendanceHistoryService::class)->employeeSummary((int) Auth::id(), $this->from, $this->to);
    }

    /**
     * @return array{from: string, to: string, shortened: bool}
     */
    #[Computed]
    public function range(): array
    {
        return app(AttendanceHistoryService::class)->cappedRange($this->from, $this->to);
    }

    public function render(): View
    {
        $history = app(AttendanceHistoryService::class);

        return view('livewire.attendance.mine', [
            'isThisWeek' => [$this->from, $this->to] === $history->currentWeek(),
        ]);
    }
}
