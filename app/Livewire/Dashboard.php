<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DashboardService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard (T-15.1), where everyone lands after login. Admin (dashboard.overview) sees today's key
 * numbers and Today's Attendance for every employee; Staff and Workers see a Clock In / Clock Out
 * shortcut and their own last 14 days.
 *
 * @property-read bool $isOverview
 * @property-read array{clocked_in: int, present: int, employees: int, needs_correction: int, pending_devices: int} $overview
 * @property-read LengthAwarePaginator<int, array<string, mixed>> $todaysAttendance
 * @property-read LengthAwarePaginator<int, array<string, mixed>> $myDays
 * @property-read Attendance|null $openAttendance
 * @property-read Collection<int, Attendance> $flagged
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    use WithPerPage;

    public function boot(): void
    {
        abort_unless(Auth::user()?->can('attendance.view-own'), 403);
    }

    #[Computed]
    public function isOverview(): bool
    {
        return $this->user()->can('dashboard.overview');
    }

    /**
     * @return array{clocked_in: int, present: int, employees: int, needs_correction: int, pending_devices: int}
     */
    #[Computed]
    public function overview(): array
    {
        abort_unless($this->isOverview, 403);

        return app(DashboardService::class)->overview();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function todaysAttendance(): LengthAwarePaginator
    {
        abort_unless($this->isOverview, 403);

        return app(DashboardService::class)->todaysAttendance($this->perPage());
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function myDays(): LengthAwarePaginator
    {
        return app(DashboardService::class)->myRecentDays($this->user(), $this->perPage());
    }

    #[Computed]
    public function openAttendance(): ?Attendance
    {
        return app(AttendanceService::class)->openSessionFor($this->user());
    }

    /**
     * @return Collection<int, Attendance>
     */
    #[Computed]
    public function flagged(): Collection
    {
        return app(AttendanceService::class)->flaggedFor($this->user());
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
