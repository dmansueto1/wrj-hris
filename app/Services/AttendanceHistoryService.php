<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Attendance history (T-11): a user's own attendance grouped by day, and everyone's attendance
 * with filters and totals. Totals add up the rows as shown (hours and labor cost of closed and
 * adjusted attendance), so void, Clocked In and flagged records never count (PRD 7.4 rule 3).
 */
class AttendanceHistoryService
{
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
     * The user's days with attendance in the range, newest first, one page of days at a time.
     * Each day has its attendance (void included, shown struck through).
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, attendances: EloquentCollection<int, Attendance>}>
     */
    public function ownDays(User $user, string $from, string $to, int $perPage): LengthAwarePaginator
    {
        $days = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->select('work_date')
            ->groupBy('work_date')
            ->orderByDesc('work_date')
            ->paginate($perPage);

        $dates = $days->getCollection()->map(fn (Attendance $day) => $day->work_date->toDateString());

        $attendances = Attendance::query()
            ->with('project')
            ->where('user_id', $user->id)
            ->whereIn('work_date', $dates)
            ->orderBy('clock_in_at')
            ->get();

        return $days->through(function (Attendance $day) use ($attendances) {
            $date = $day->work_date->toDateString();

            return [
                'date' => $day->work_date,
                'attendances' => $attendances->filter(fn (Attendance $attendance) => $attendance->work_date->toDateString() === $date)->values(),
            ];
        });
    }

    /**
     * The user's total hours and number of attendance records (void left out) in the range.
     *
     * @return array{hours: string, count: int}
     */
    public function ownTotals(User $user, string $from, string $to): array
    {
        $query = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to]);

        return [
            'hours' => $this->hundredths((string) (clone $query)->counted()->sum('hours_worked')),
            'count' => (clone $query)->where('status', '!=', Attendance::STATUS_VOID)->count(),
        ];
    }

    /**
     * Everyone's attendance matching the filters, newest first, with the employee (archived
     * ones too) and project loaded.
     *
     * @param  array{from: string, to: string, user: ?int, project: ?int, status: ?string}  $filters
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function all(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['user' => fn ($query) => $query->withTrashed(), 'project'])
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Total hours and labor cost over every attendance record matching the filters (not just
     * one page), counting closed and adjusted ones only.
     *
     * @param  array{from: string, to: string, user: ?int, project: ?int, status: ?string}  $filters
     * @return array{hours: string, labor_cost: string}
     */
    public function totals(array $filters): array
    {
        $sums = $this->filtered($filters)
            ->counted()
            ->toBase()
            ->selectRaw('COALESCE(SUM(hours_worked), 0) AS hours, COALESCE(SUM(labor_cost), 0) AS labor_cost')
            ->first();

        return [
            'hours' => $this->hundredths((string) ($sums->hours ?? '0')),
            'labor_cost' => $this->hundredths((string) ($sums->labor_cost ?? '0')),
        ];
    }

    /**
     * How many people are clocked in right now.
     */
    public function clockedInNow(): int
    {
        return Attendance::query()->open()->count();
    }

    /**
     * @param  array{from: string, to: string, user: ?int, project: ?int, status: ?string}  $filters
     * @return Builder<Attendance>
     */
    private function filtered(array $filters): Builder
    {
        return Attendance::query()
            ->whereBetween('work_date', [$filters['from'], $filters['to']])
            ->when($filters['user'], fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['project'], fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when(
                isset(Attendance::STATUSES[$filters['status'] ?? '']),
                fn (Builder $query) => $query->where('status', $filters['status']),
            );
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
