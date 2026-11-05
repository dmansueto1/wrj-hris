<?php

namespace App\Support\Costing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * T-11.3: the minutes of an attendance that count toward hours and labor cost. Only the time
 * inside the shift windows counts; with no shift (Flexible) every minute counts (PRD 7.1).
 * Pure: no database.
 */
class ShiftMinutes
{
    /**
     * @param  array<int, array{start: string|CarbonInterface, end: string|CarbonInterface}>|null  $windows  null = Flexible, [] = rest day
     */
    public static function counted(CarbonInterface $in, CarbonInterface $out, ?array $windows): int
    {
        $clocked = self::between($in, $out);

        if ($windows === null) {
            return $clocked;
        }

        // Merge overlapping windows first so no minute counts twice.
        $ranges = collect($windows)
            ->map(fn (array $window) => [Carbon::parse($window['start']), Carbon::parse($window['end'])])
            ->sortBy(fn (array $range) => $range[0]->getTimestamp())
            ->values();

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start->lte($merged[$last][1])) {
                $merged[$last][1] = $end->gt($merged[$last][1]) ? $end : $merged[$last][1];
            } else {
                $merged[] = [$start, $end];
            }
        }

        $counted = 0;
        foreach ($merged as [$start, $end]) {
            $counted += self::between($in->gt($start) ? $in : $start, $out->lt($end) ? $out : $end);
        }

        return min($counted, $clocked);
    }

    /**
     * Whole minutes from one time to another; 0 when the second is not after the first.
     */
    private static function between(CarbonInterface $from, CarbonInterface $to): int
    {
        return $to->gt($from) ? (int) $from->diffInMinutes($to) : 0;
    }
}
