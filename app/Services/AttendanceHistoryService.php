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
     * Daily attendance (T-11.4): one item per employee for the date, sorted by last name, Present
     * when they have attendance that is not void. Inactive or archived employees appear only when
     * they have attendance on that date, so no record is hidden.
     *
     * @return LengthAwarePaginator<int, array{user: User, present: bool, attendances: EloquentCollection<int, Attendance>}>
     */
    public function daily(string $date, ?int $userId, int $perPage, string $project, string $sortBy): LengthAwarePaginator
    {
        $users = $this->dailyEmployees($date, $userId, $project, $sortBy)->paginate($perPage);

        $attendances = $this->presentOn($date)
            ->with('project')
            ->whereIn('user_id', $users->getCollection()->modelKeys())
            ->orderBy('clock_in_at')
            ->get();

        return $users->through(function (User $user) use ($attendances) {
            // Each attendance gets its row's user (archived ones too) without another query.
            $own = $attendances->filter(fn (Attendance $attendance) => $attendance->user_id === $user->id)->values()
                ->each(fn (Attendance $attendance) => $attendance->setRelation('user', $user));

            return ['user' => $user, 'present' => $own->isNotEmpty(), 'attendances' => $own];
        });
    }

    /**
     * Counts for the Daily attendance stat cards, and the date's labor cost (closed and adjusted).
     *
     * @return array{employees: int, present: int, absent: int, labor_cost: string}
     */
    public function dailySummary(string $date, ?int $userId, ?int $project, ?string $sortBy): array
    {
        $employees = $this->dailyEmployees($date, $userId, $project, $sortBy)->count();

        $present = $this->presentOn($date)
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->distinct()
            ->count('user_id');

        $laborCost = Attendance::query()
            ->counted()
            ->where('work_date', $date)
            ->when($userId, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->sum('labor_cost');

        return [
            'employees' => $employees,
            'present' => $present,
            'absent' => $employees - $present,
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
     * @return LengthAwarePaginator<int, array{date: Carbon, present: bool, attendances: EloquentCollection<int, Attendance>}>
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

        $items = $dates->map(function (Carbon $date) use ($attendances) {
            $own = $attendances->filter(fn (Attendance $attendance) => $attendance->work_date->isSameDay($date))->values();

            return ['date' => $date, 'present' => $own->isNotEmpty(), 'attendances' => $own];
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

        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

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
            'absent_days' => $days - $presentDays,
            'hours' => $this->hundredths((string) ($sums->hours ?? '0')),
            'labor_cost' => $this->hundredths((string) ($sums->labor_cost ?? '0')),
        ];
    }

    /**
     * Employees listed on a date: active employees, plus anyone (inactive or archived too) with
     * attendance that day.
     *
     * @return Builder<User>
     */
    private function dailyEmployees(string $date, ?int $userId, ?int $project, ?string $sortBy): Builder
    {
        $user = User::withTrashed()
            ->where(function (Builder $query) use ($date) {
                $query->whereIn('users.id', $this->employees()->select('users.id'))
                    ->orWhereHas('attendances', fn (Builder $attendance) => $attendance
                        ->where('work_date', $date)
                        ->whereIn('status', Attendance::PRESENT_STATUSES));
            })
            ->when($userId, fn (Builder $query, int $id) => $query->whereKey($id));

            // if($sortBy === 'name') {
                return $user->orderBy('last_name')
                    ->orderBy('first_name')
                    ->orderBy('id');
            // } 
            // elseif($sortBy === 'project') {
            //     return $user->leftJoin('attendances', function ($join) use ($project) {
            //         $join->leftJoin('projects', 'projects.id', '=', 'attendances.project_id')
            //             ->where('attendances.id', '=', $project);
            //     })
            //         ->orderBy('projects.name')
            //         ->orderBy('last_name')
            //         ->orderBy('first_name');
            // } 
            // elseif($sortBy === 'worked_time') {
            //     return $user->leftJoin('attendances', function ($join) use ($date) {
            //         $join->on('users.id', '=', 'attendances.user_id')
            //             ->where('attendances.work_date', '=', $date)
            //             ->whereIn('attendances.status', Attendance::PRESENT_STATUSES);
            //     })
            //     ->select('users.*')
            //     ->orderByRaw('COALESCE(attendances.hours_worked, 0) DESC')
            //     ->orderBy('last_name')
            //     ->orderBy('first_name')
            //     ->orderBy('id');
            // }
    }

    /**
     * Attendance that makes its employee Present on the date.
     *
     * @return Builder<Attendance>
     */
    private function presentOn(string $date): Builder
    {
        return Attendance::query()->where('work_date', $date)->whereIn('status', Attendance::PRESENT_STATUSES);
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
