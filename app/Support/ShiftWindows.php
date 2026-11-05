<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Shift windows as people read them: "8:00 AM–12:00 PM, 1:00 PM–5:00 PM" (T-11.3).
 */
class ShiftWindows
{
    /**
     * @param  array<int, array{start: string|CarbonInterface, end: string|CarbonInterface}|array{0: CarbonInterface, 1: CarbonInterface}>  $windows
     */
    public static function label(array $windows): string
    {
        return collect($windows)
            ->map(function (array $window) {
                $start = Carbon::parse($window['start'] ?? $window[0]);
                $end = Carbon::parse($window['end'] ?? $window[1]);

                return $start->format('g:i A').'–'.$end->format('g:i A');
            })
            ->implode(', ');
    }
}
