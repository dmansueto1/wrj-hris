<?php

namespace App\Support;

use App\Models\Attendance;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * T-16.1: how late someone was on one day, against the shift saved on the attendance at clock-in.
 * Display only: late minutes are already unpaid, because only time inside the shift counts (T-11.3).
 * Pure: no database.
 *
 * Per shift window: no attendance in the window (skipped) is not late; clocked in at or before its
 * start (or still in from the morning) is not late; otherwise the whole minutes from the start to
 * the first clock-in. Late within the grace period counts as 0; above it, the full minutes count.
 */
class Lateness
{
    /**
     * @param  iterable<Attendance>  $attendances  the day's non-void attendance
     * @param  array<int, array{start: string, end: string}>|null  $windows  the shift saved at clock-in; null = Flexible, [] = rest day
     */
    public static function minutes(iterable $attendances, ?array $windows, int $graceMinutes, CarbonInterface $now): int
    {
        if (empty($windows)) {
            return 0;
        }

        $attendances = collect($attendances);
        $late = 0;

        foreach ($windows as $window) {
            $start = Carbon::parse($window['start']);
            $end = Carbon::parse($window['end']);

            $inWindow = $attendances->filter(fn (Attendance $attendance) => $attendance->clock_in_at->lt($end)
                && ($attendance->clock_out_at ?? $now)->gt($start));

            if ($inWindow->isEmpty()) {
                continue;
            }

            $firstIn = $inWindow->min(fn (Attendance $attendance) => $attendance->clock_in_at->getTimestamp());
            $minutes = $firstIn <= $start->getTimestamp() ? 0 : intdiv($firstIn - $start->getTimestamp(), 60);

            if ($minutes > $graceMinutes) {
                $late += $minutes;
            }
        }

        return $late;
    }
}
