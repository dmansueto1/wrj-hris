<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Attendance history (T-11, T-11.4): every employee's presence on a date, and one employee's days
 * over a range (All Attendance's By employee tab and My Attendance). Totals add up the rows as shown (hours
 * and labor cost of closed and adjusted attendance), so void, Clocked In and flagged records never
 * count (PRD 7.4 rule 3).
 */
class AttendanceHistoryService
{
    /** Daily attendance sort orders: last name A–Z, project A–Z, most worked time first. */
    public const DAILY_SORTS = ['name', 'project', 'worked_time'];

    /** The By employee tab shows at most this many days (T-11.4). */
    public const MAX_RANGE_DAYS = 92;

    /**
     * The current week, Monday to Sunday, as ['2026-10-19', '2026-10-25'].
     *
     * @return array{0: string, 1: string}
     */
    public function currentWeek(): array
    {
        return [
            now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            now()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        ];
    }

    /**
     * A valid date range: an invalid date falls back to the current week's end, and a reversed
     * range is swapped.
     *
     * @return array{0: string, 1: string}
     */
    public function range(string $from, string $to): array
    {
        [$weekStart, $weekEnd] = $this->currentWeek();

        $from = $this->validDate($from) ?? $weekStart;
        $to = $this->validDate($to) ?? $weekEnd;

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /**
     * A valid date, or today when it is not one ("2026-10-21").
     */
    public function date(string $date): string
    {
        return $this->validDate($date) ?? now()->toDateString();
    }

    /**
     * Everyone who can clock in, active and not archived, sorted by last name then first name.
     *
     * @return Builder<User>
     */
    public function employees(): Builder
    {
        return User::query()
            ->permission('attendance.clock')
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * Daily attendance (T-11.4): one item per employee for the date, Present when they have
     * attendance that is not void. Inactive or archived employees appear only when they have
     * attendance on that date, so no record is hidden. With a project, only the employees who
     * worked on it that day are listed, with only that project's attendance.
     *
     * Sorted by `name` (last name A–Z), `project` (the project of their first attendance that day,
     * A–Z) or `worked_time` (most hours that day first); absent employees come after, by name.
     *
     * A future date is neither Present nor Absent yet: its items have `future = true`.
     *
     * @return LengthAwarePaginator<int, array{user: User, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}>
     */
    public function daily(string $date, ?int $userId, ?int $projectId, string $sortBy, int $perPage): LengthAwarePaginator
    {
        $users = $this->sorted($this->dailyEmployees($date, $userId, $projectId), $date, $projectId, $sortBy)->paginate($perPage);

        $attendances = $this->presentOn($date, $projectId)
            ->with('project')
            ->whereIn('user_id', $users->getCollection()->modelKeys())
            ->orderBy('clock_in_at')
            ->get();

        $future = $date > now()->toDateString();

        return $users->through(function (User $user) use ($attendances, $future) {
            // Each attendance gets its row's user (archived ones too) without another query.
            $own = $attendances->filter(fn (Attendance $attendance) => $attendance->user_id === $user->id)->values()
                ->each(fn (Attendance $attendance) => $attendance->setRelation('user', $user));

            return ['user' => $user, 'present' => $own->isNotEmpty(), 'future' => $future, 'attendances' => $own];
        });
    }

    /**
     * Counts for the Daily attendance stat cards, and the date's labor cost (closed and adjusted),
     * for the same employee and project filters as daily().
     *
     * @return array{employees: int, present: int, absent: int, labor_cost: string}
     */
    public function dailySummary(string $date, ?int $userId, ?int $projectId): array
    {
        $employees = $this->dailyEmployees($date, $userId, $projectId)->count();

        $present = $this->presentOn($date, $projectId)
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->distinct()
            ->count('user_id');

        $laborCost = Attendance::query()
            ->counted()
            ->where('work_date', $date)
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($projectId, fn (Builder $query, int $id) => $query->where('project_id', $id))
            ->sum('labor_cost');

        return [
            'employees' => $employees,
            'present' => $present,
            // Nobody is absent on a day that has not happened yet.
            'absent' => $date > now()->toDateString() ? 0 : $employees - $present,
            'labor_cost' => $this->hundredths((string) $laborCost),
        ];
    }

    /**
     * The By employee range, at most 92 days ending at `to` (T-11.4).
     *
     * @return array{from: string, to: string, shortened: bool}
     */
    public function cappedRange(string $from, string $to): array
    {
        $earliest = Carbon::parse($to)->subDays(self::MAX_RANGE_DAYS - 1)->toDateString();

        return $from < $earliest
            ? ['from' => $earliest, 'to' => $to, 'shortened' => true]
            : ['from' => $from, 'to' => $to, 'shortened' => false];
    }

    /**
     * By employee (T-11.4): one item per date in the range, newest first, one page of days at a
     * time. Days without attendance (void left out) are Absent.
     *
     * Days after today have `future = true`: neither Present nor Absent yet.
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, present: bool, future: bool, attendances: EloquentCollection<int, Attendance>}>
     */
    public function employeeDays(int $userId, string $from, string $to, int $perPage): LengthAwarePaginator
    {
        ['from' => $from, 'to' => $to] = $this->cappedRange($from, $to);

        $total = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $page = Paginator::resolveCurrentPage();
        $first = ($page - 1) * $perPage;
        $last = min($page * $perPage, $total) - 1;

        // A page past the end (old link, shorter range) has no days, never days outside the range.
        $dates = $first > $last
            ? collect()
            : collect(range($first, $last))->map(fn (int $offset) => Carbon::parse($to)->subDays($offset));

        $attendances = $dates->isEmpty()
            ? new EloquentCollection
            : Attendance::query()
                ->with(['project', 'user' => fn ($query) => $query->withTrashed()])
                ->where('user_id', $userId)
                ->whereIn('status', Attendance::PRESENT_STATUSES)
                ->whereBetween('work_date', [$dates->last()->toDateString(), $dates->first()->toDateString()])
                ->orderBy('clock_in_at')
                ->get();

        $today = now()->toDateString();

        $items = $dates->map(function (Carbon $date) use ($attendances, $today) {
            $own = $attendances->filter(fn (Attendance $attendance) => $attendance->work_date->isSameDay($date))->values();

            return ['date' => $date, 'present' => $own->isNotEmpty(), 'future' => $date->toDateString() > $today, 'attendances' => $own];
        })->values()->all();

        return new Paginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Present and absent days, hours and labor cost (closed and adjusted) over the range.
     *
     * @return array{present_days: int, absent_days: int, hours: string, labor_cost: string}
     */
    public function employeeSummary(int $userId, string $from, string $to): array
    {
        ['from' => $from, 'to' => $to] = $this->cappedRange($from, $to);

        // Absent days are counted only up to today: later days have not happened yet.
        $today = now()->toDateString();
        $pastDays = $from > $today ? 0 : (int) Carbon::parse($from)->diffInDays(Carbon::parse(min($to, $today))) + 1;

        $presentDays = Attendance::query()
            ->where('user_id', $userId)
            ->whereIn('status', Attendance::PRESENT_STATUSES)
            ->whereBetween('work_date', [$from, $to])
            ->distinct()
            ->count('work_date');

        $sums = Attendance::query()
            ->counted()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$from, $to])
            ->toBase()
            ->selectRaw('COALESCE(SUM(hours_worked), 0) AS hours, COALESCE(SUM(labor_cost), 0) AS labor_cost')
            ->first();

        return [
            'present_days' => $presentDays,
            'absent_days' => max(0, $pastDays - $presentDays),
            'hours' => $this->hundredths((string) ($sums->hours ?? '0')),
            'labor_cost' => $this->hundredths((string) ($sums->labor_cost ?? '0')),
        ];
    }

    /**
     * Employees listed on a date: active employees, plus anyone (inactive or archived too) with
     * attendance that day. With a project, only those who worked on it that day. Not sorted.
     *
     * @return Builder<User>
     */
    private function dailyEmployees(string $date, ?int $userId, ?int $projectId): Builder
    {
        return User::withTrashed()
            ->when(
                $projectId,
                fn (Builder $query, int $id) => $query->whereHas('attendances', fn (Builder $attendance) => $attendance
                    ->where('work_date', $date)
                    ->whereIn('status', Attendance::PRESENT_STATUSES)
                    ->where('project_id', $id)),
                fn (Builder $query) => $query->where(function (Builder $query) use ($date) {
                    $query->whereIn('users.id', $this->employees()->select('users.id'))
                        ->orWhereHas('attendances', fn (Builder $attendance) => $attendance
                            ->where('work_date', $date)
                            ->whereIn('status', Attendance::PRESENT_STATUSES));
                }),
            )
            ->when($userId, fn (Builder $query, int $id) => $query->whereKey($id));
    }

    /**
     * Order the Daily attendance list. Sorting by project or worked time uses a subquery per
     * employee (no join), so an employee with two attendance records is still one row.
     *
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    private function sorted(Builder $users, string $date, ?int $projectId, string $sortBy): Builder
    {
        $theirs = fn () => $this->presentOn($date, $projectId)->whereColumn('attendances.user_id', 'users.id');
        // 1 when present that day, 0 when absent: ordering by it descending puts absent employees last.
        $present = fn () => $theirs()->selectRaw('COUNT(*) > 0');

        if ($sortBy === 'project') {
            // The project of their first attendance that day, A–Z.
            $firstProject = $theirs()
                ->join('projects', 'projects.id', '=', 'attendances.project_id')
                ->orderBy('attendances.clock_in_at')
                ->limit(1)
                ->select('projects.name');

            $users->orderByDesc($present())->orderBy($firstProject);
        } elseif ($sortBy === 'worked_time') {
            // Most hours that day first (Clocked In counts as 0 until clocked out).
            $users->orderByDesc($theirs()->selectRaw('COALESCE(SUM(attendances.hours_worked), 0)'))
                ->orderByDesc($present());
        }

        return $users->orderBy('last_name')->orderBy('first_name')->orderBy('users.id');
    }

    /**
     * Attendance that makes its employee Present on the date.
     *
     * @return Builder<Attendance>
     */
    private function presentOn(string $date, ?int $projectId = null): Builder
    {
        // Qualified: the project sort joins projects, which has its own status column.
        return Attendance::query()
            ->where('attendances.work_date', $date)
            ->whereIn('attendances.status', Attendance::PRESENT_STATUSES)
            ->when($projectId, fn (Builder $query, int $id) => $query->where('attendances.project_id', $id));
    }

    /**
     * A DECIMAL(…, 2) sum from the database as a two-decimal string ("9" → "9.00"). No floats.
     */
    private function hundredths(string $value): string
    {
        return Money::fromCentavos(Money::toCentavos($value));
    }

    private function validDate(string $value): ?string
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== null && $date->toDateString() === $value ? $value : null;
    }
}
