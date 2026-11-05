<?php

namespace Tests\Unit;

use App\Support\DaySlots;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DaySlotsTest extends TestCase
{
    private const TWO_SHIFTS = [
        ['start' => '2026-10-19 08:00', 'end' => '2026-10-19 12:00'],
        ['start' => '2026-10-19 13:00', 'end' => '2026-10-19 17:00'],
    ];

    /**
     * @return array<string, array{?array<int, array{start: string, end: string}>, string, string, string}>
     */
    public static function cases(): array
    {
        return [
            'two shifts: morning' => [self::TWO_SHIFTS, '07:50', '12:24', DaySlots::MORNING],
            'two shifts: afternoon' => [self::TWO_SHIFTS, '12:58', '17:03', DaySlots::AFTERNOON],
            'two shifts: no lunch clock-out' => [self::TWO_SHIFTS, '07:55', '17:10', DaySlots::WHOLE],
            'two shifts: lunch only, nearer the morning' => [self::TWO_SHIFTS, '12:05', '12:20', DaySlots::MORNING],
            'two shifts: lunch only, nearer the afternoon' => [self::TWO_SHIFTS, '12:40', '12:55', DaySlots::AFTERNOON],
            'one shift: always both columns' => [[['start' => '2026-10-19 08:00', 'end' => '2026-10-19 17:00']], '08:02', '11:00', DaySlots::WHOLE],
            'flexible: morning' => [null, '07:00', '11:30', DaySlots::MORNING],
            'flexible: afternoon' => [null, '13:00', '17:00', DaySlots::AFTERNOON],
            'flexible: whole day' => [null, '07:00', '17:00', DaySlots::WHOLE],
            'rest day: by the clock' => [[], '08:00', '11:00', DaySlots::MORNING],
        ];
    }

    /**
     * @param  array<int, array{start: string, end: string}>|null  $windows
     */
    #[DataProvider('cases')]
    public function testEachAttendanceGoesInTheRightColumn(?array $windows, string $in, string $out, string $slot): void
    {
        $this->assertSame($slot, DaySlots::classify(Carbon::parse("2026-10-19 {$in}"), Carbon::parse("2026-10-19 {$out}"), $windows));
    }
}
