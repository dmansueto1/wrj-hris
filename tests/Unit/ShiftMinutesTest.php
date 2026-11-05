<?php

namespace Tests\Unit;

use App\Support\Costing\LaborCost;
use App\Support\Costing\ShiftMinutes;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-11.3: only the minutes inside the shift count, then LaborCost turns them into hours and pesos.
 */
class ShiftMinutesTest extends TestCase
{
    private const MORNING = ['start' => '2026-10-19 08:00', 'end' => '2026-10-19 12:00'];

    private const AFTERNOON = ['start' => '2026-10-19 13:00', 'end' => '2026-10-19 17:00'];

    /**
     * @return array<string, array{?array<int, array{start: string, end: string}>, string, string, string, int, string, string}>
     */
    public static function cases(): array
    {
        return [
            'early in, late out' => [[self::MORNING], '2026-10-19 07:50', '2026-10-19 12:24', '80.00', 240, '4.00', '320.00'],
            'late in' => [[self::MORNING], '2026-10-19 08:15', '2026-10-19 12:00', '80.00', 225, '3.75', '300.00'],
            'early out, uneven' => [[self::MORNING], '2026-10-19 07:30', '2026-10-19 11:20', '80.00', 200, '3.33', '266.67'],
            'whole day, two shifts, no lunch clock-out' => [[self::MORNING, self::AFTERNOON], '2026-10-19 07:55', '2026-10-19 17:10', '80.00', 480, '8.00', '640.00'],
            'afternoon only' => [[self::MORNING, self::AFTERNOON], '2026-10-19 12:30', '2026-10-19 17:00', '80.00', 240, '4.00', '320.00'],
            'fully outside' => [[self::MORNING], '2026-10-19 18:00', '2026-10-19 20:00', '80.00', 0, '0.00', '0.00'],
            'rest day' => [[], '2026-10-25 08:00', '2026-10-25 12:00', '80.00', 0, '0.00', '0.00'],
            'flexible (PRD 10.2)' => [null, '2026-10-19 07:00', '2026-10-19 11:30', '80.00', 270, '4.50', '360.00'],
            'night shift at 95.50' => [[['start' => '2026-10-19 22:00', 'end' => '2026-10-20 06:00']], '2026-10-19 21:45', '2026-10-20 06:20', '95.50', 480, '8.00', '764.00'],
        ];
    }

    /**
     * @param  array<int, array{start: string, end: string}>|null  $windows
     */
    #[DataProvider('cases')]
    public function testOnlyTheMinutesInsideTheShiftCount(?array $windows, string $in, string $out, string $rate, int $minutes, string $hours, string $cost): void
    {
        $counted = ShiftMinutes::counted(Carbon::parse($in), Carbon::parse($out), $windows);

        $this->assertSame($minutes, $counted);
        $this->assertSame(['hours' => $hours, 'cost' => $cost], LaborCost::forMinutes($counted, $rate));
    }

    public function testNeverMoreThanTheClockedMinutesAndNeverNegative(): void
    {
        // Two overlapping windows cannot count the same minutes twice.
        $overlapping = [self::MORNING, ['start' => '2026-10-19 10:00', 'end' => '2026-10-19 12:00']];
        $this->assertSame(240, ShiftMinutes::counted(Carbon::parse('2026-10-19 08:00'), Carbon::parse('2026-10-19 12:00'), $overlapping));

        // Out before in (bad data) counts nothing.
        $this->assertSame(0, ShiftMinutes::counted(Carbon::parse('2026-10-19 12:00'), Carbon::parse('2026-10-19 08:00'), [self::MORNING]));
    }
}
