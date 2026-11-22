<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Support\Costing\LaborCost;
use App\Support\Lateness;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-16.1: late minutes of one day against the shift saved at clock-in. Work date 2026-10-21.
 */
class LatenessTest extends TestCase
{
    private const DAY = '2026-10-21';

    /**
     * @return array<string, array{list<array{string, string}>|null, list<array{string, ?string}>, int, int}>
     */
    public static function cases(): array
    {
        $morning = ['08:00', '12:00'];
        $afternoon = ['13:00', '17:00'];

        return [
            'late both halves' => [[$morning, $afternoon], [['08:15', '12:00'], ['13:10', '17:00']], 0, 25],
            'on time' => [[$morning, $afternoon], [['07:55', '12:00'], ['12:58', '17:00']], 0, 0],
            'exactly at start' => [[$morning], [['08:00', '12:00']], 0, 0],
            'through lunch' => [[$morning, $afternoon], [['08:05', '17:00']], 0, 5],
            'morning skipped' => [[$morning, $afternoon], [['13:20', '17:00']], 0, 20],
            'back after a break' => [[$morning], [['08:00', '10:00'], ['10:30', '12:00']], 0, 0],
            'one shift' => [[['08:00', '17:00']], [['09:30', '17:00']], 0, 90],
            'night shift' => [[['22:00', '+06:00']], [['22:45', '+06:00']], 0, 45],
            'seconds dropped' => [[$morning], [['08:15:59', '12:00']], 0, 15],
            'still clocked in (now 10:00)' => [[$morning], [['08:20', null]], 0, 20],
            'inside grace' => [[$morning], [['08:04', '12:00']], 5, 0],
            'at grace' => [[$morning], [['08:05', '12:00']], 5, 0],
            'over grace counts in full' => [[$morning], [['08:07', '12:00']], 5, 7],
            'rest day' => [[], [['08:30', '12:00']], 0, 0],
            'flexible' => [null, [['09:00', '12:00']], 0, 0],
            'no attendance' => [[$morning], [], 0, 0],
        ];
    }

    /**
     * @param  list<array{string, string}>|null  $windows  times on the work date; "+06:00" is the next day
     * @param  list<array{string, ?string}>  $attendances  clock in / out times; null out = still Clocked In
     */
    #[DataProvider('cases')]
    public function testLateMinutes(?array $windows, array $attendances, int $grace, int $expected): void
    {
        $at = fn (string $time) => str_starts_with($time, '+')
            ? Carbon::parse(self::DAY.' '.substr($time, 1))->addDay()
            : Carbon::parse(self::DAY.' '.$time);

        $shift = $windows === null ? null : array_map(fn (array $window) => [
            'start' => $at($window[0])->format('Y-m-d H:i'),
            'end' => $at($window[1])->format('Y-m-d H:i'),
        ], $windows);

        $records = array_map(fn (array $times) => (new Attendance)->forceFill([
            'clock_in_at' => $at($times[0]),
            'clock_out_at' => $times[1] === null ? null : $at($times[1]),
        ]), $attendances);

        $this->assertSame($expected, Lateness::minutes($records, $shift, $grace, $at('10:00')));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function hours(): array
    {
        return [
            '25 minutes' => [25, '0.42'],
            '5 minutes' => [5, '0.08'],
            '20 minutes' => [20, '0.33'],
            '90 minutes' => [90, '1.50'],
            '7 minutes' => [7, '0.12'],
            '15 minutes' => [15, '0.25'],
        ];
    }

    #[DataProvider('hours')]
    public function testLateShowsAsHoursLikeWorkedTime(int $minutes, string $hours): void
    {
        $this->assertSame($hours, LaborCost::hoursForMinutes($minutes));
    }
}
