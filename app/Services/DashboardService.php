<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Dashboard (T-15.1): today's key numbers and Today's Attendance for the Admin, and each
 * user's own recent attendance. Built on AttendanceHistoryService, so the rows match the Daily
 * attendance tab and My Attendance.
 */
class DashboardService
{
    /** The personal dashboard shows this many days, today included. */
    public const MY_DAYS = 14;

    public function __construct(
        private readonly AttendanceHistoryService $history,
        private readonly ProjectExpenseService $expenses,
    ) {}

    /**
     * Today's numbers for the admin cards: users Clocked In now, employees present today out of
     * all employees, flagged attendance, and device requests waiting for approval.
     *
     * @return array{clocked_in: int, present: int, employees: int, needs_correction: int, pending_devices: int}
     */
    public function overview(): array
    {
        $today = $this->history->dailySummary(now()->toDateString(), null, null);

        return [
            'clocked_in' => Attendance::query()->open()->distinct()->count('user_id'),
            'present' => $today['present'],
            'employees' => $today['employees'],
            'needs_correction' => $this->history->flaggedCount(),
            'pending_devices' => Device::query()->where('status', Device::STATUS_PENDING)->count(),
        ];
    }

    /**
     * The Expenses row for the Admin: running expenses of every project since the 1st of this month
     * (labor counts once clocked out, so "today" alone would read low), and how many projects are at
     * 80% or more of their budget, and over it.
     *
     * @return array{month: array{labor: string, materials: string, running: string}, budget: array{warning: int, over: int}}
     */
    public function expenses(): array
    {
        return [
            'month' => $this->expenses->overallTotals(now()->startOfMonth()->toDateString(), now()->toDateString()),
            'budget' => $this->expenses->budgetWatch(),
        ];
    }

    /**
     * Every employee today by last name, the ones not clocked in yet included (as the Daily
     * attendance tab with no filters).
     *
     * @return LengthAwarePaginator<int, array{user: User, present: bool, rest_day: bool, future: bool, attendances: Collection<int, Attendance>}>
     */
    public function todaysAttendance(int $perPage): LengthAwarePaginator
    {
        return $this->history->daily(now()->toDateString(), null, null, 'name', $perPage);
    }

    /**
     * The user's last MY_DAYS days, newest first (today on top).
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, present: bool, rest_day: bool, future: bool, attendances: Collection<int, Attendance>}>
     */
    public function myRecentDays(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->history->employeeDays(
            $user->id,
            now()->subDays(self::MY_DAYS - 1)->toDateString(),
            now()->toDateString(),
            $perPage,
        );
    }
}
