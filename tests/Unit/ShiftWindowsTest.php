<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserShiftService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ShiftWindowsTest extends TestCase
{
    /**
     * @param  list<array{0: CarbonInterface, 1: CarbonInterface}>|null  $windows
     * @return list<array{string, string}>|null
     */
    private function format(?array $windows): ?array
    {
        return $windows === null ? null : array_map(
            fn (array $window) => [$window[0]->format('Y-m-d H:i'), $window[1]->format('Y-m-d H:i')],
            $windows,
        );
    }

    public function testTwoShiftsOnAMondayGiveAMorningAndAnAfternoonWindow(): void
    {
        $day = ['is_rest_day' => false, 'first_in' => '08:00', 'first_out' => '12:00', 'second_in' => '13:00', 'second_out' => '17:00'];

        $this->assertSame(
            [['2026-10-19 08:00', '2026-10-19 12:00'], ['2026-10-19 13:00', '2026-10-19 17:00']],
            $this->format(UserShiftService::windows(User::SHIFT_TWO, $day, Carbon::parse('2026-10-19'))),
        );
    }

    public function testARestDayHasNoWindows(): void
    {
        $day = ['is_rest_day' => true, 'first_in' => null, 'first_out' => null, 'second_in' => null, 'second_out' => null];

        $this->assertSame([], UserShiftService::windows(User::SHIFT_TWO, $day, Carbon::parse('2026-10-25')));
        $this->assertSame([], UserShiftService::windows(User::SHIFT_ONE, null, Carbon::parse('2026-10-25')));
    }

    public function testFlexibleHasNoSchedule(): void
    {
        $this->assertNull(UserShiftService::windows(User::SHIFT_FLEXIBLE, null, Carbon::parse('2026-10-19')));
    }

    public function testANightShiftEndsTheNextDay(): void
    {
        $day = ['is_rest_day' => false, 'first_in' => '22:00', 'first_out' => '06:00', 'second_in' => null, 'second_out' => null];

        $this->assertSame(
            [['2026-10-19 22:00', '2026-10-20 06:00']],
            $this->format(UserShiftService::windows(User::SHIFT_ONE, $day, Carbon::parse('2026-10-19'))),
        );
    }
}
