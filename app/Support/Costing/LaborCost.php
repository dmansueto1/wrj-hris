<?php

namespace App\Support\Costing;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Labor cost of one session (PRD 7.1 and 10.2), computed in whole centavos with half-up rounding.
 */
class LaborCost
{
    /**
     * Hours and cost for a number of worked minutes at an hourly rate.
     * The cost comes from the exact minutes, never from the rounded hours.
     *
     * @return array{hours: string, cost: string}
     */
    public static function forMinutes(int $minutes, string $hourlyRate): array
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException("Minutes cannot be negative [{$minutes}].");
        }

        $rateCentavos = Money::toCentavos($hourlyRate);

        $costCentavos = intdiv($minutes * $rateCentavos + 30, 60);
        $hoursHundredths = intdiv($minutes * 100 + 30, 60);

        return [
            'hours' => Money::fromCentavos($hoursHundredths),
            'cost' => Money::fromCentavos($costCentavos),
        ];
    }
}
