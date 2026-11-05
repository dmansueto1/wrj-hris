<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which column an attendance goes in on the attendance tables: Morning, Afternoon, or both
 * ("whole" — e.g. a one-shift day or a two-shift day without a lunch clock-out). Pure: no database.
 *
 * Morning ends at the end of the first shift window and the afternoon starts at the second one
 * (Two shifts a day); otherwise at 12:00 and 13:00. A One-shift attendance always spans both.
 */
class DaySlots
{
    public const MORNING = 'morning';

    public const AFTERNOON = 'afternoon';

    public const WHOLE = 'whole';

    /**
     * @param  array<int, array{start: string, end: string}>|null  $windows  the shift saved at clock-in
     */
    public static function classify(CarbonInterface $in, CarbonInterface $out, ?array $windows): string
    {
        if ($windows !== null && count($windows) === 1) {
            return self::WHOLE;
        }

        if ($windows !== null && count($windows) >= 2) {
            $morningEnd = Carbon::parse($windows[0]['end']);
            $afternoonStart = Carbon::parse($windows[1]['start']);
        } else {
            $morningEnd = Carbon::parse($in->toDateString().' 12:00');
            $afternoonStart = Carbon::parse($in->toDateString().' 13:00');
        }

        $morning = $in->lt($morningEnd) ? (int) $in->diffInMinutes($out->lt($morningEnd) ? $out : $morningEnd) : 0;
        $afternoon = $out->gt($afternoonStart) ? (int) ($in->gt($afternoonStart) ? $in : $afternoonStart)->diffInMinutes($out) : 0;

        return match (true) {
            $morning > 0 && $afternoon > 0 => self::WHOLE,
            $morning > 0 => self::MORNING,
            $afternoon > 0 => self::AFTERNOON,
            // Entirely in the lunch gap: the nearer half.
            $in->lt($morningEnd->copy()->addSeconds((int) ($morningEnd->diffInSeconds($afternoonStart) / 2))) => self::MORNING,
            default => self::AFTERNOON,
        };
    }
}
