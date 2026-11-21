<?php

namespace Tests\Unit;

use App\Support\Greeting;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GreetingTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function times(): array
    {
        return [
            'just after midnight' => ['00:05', 'Good morning'],
            'early shift' => ['06:30', 'Good morning'],
            'last morning minute' => ['11:59', 'Good morning'],
            'noon' => ['12:00', 'Good afternoon'],
            'last afternoon minute' => ['17:59', 'Good afternoon'],
            'six in the evening' => ['18:00', 'Good evening'],
            'late' => ['23:30', 'Good evening'],
        ];
    }

    #[DataProvider('times')]
    public function testTheGreetingFollowsTheTimeOfDay(string $time, string $greeting): void
    {
        $this->assertSame($greeting, Greeting::for(Carbon::parse('2026-11-19 '.$time, 'Asia/Manila')));
    }

    public function testTheIconIsTheSunByDayAndTheMoonAtNight(): void
    {
        $this->assertSame('sun', Greeting::icon(Carbon::parse('2026-11-19 07:00', 'Asia/Manila')));
        $this->assertSame('sun', Greeting::icon(Carbon::parse('2026-11-19 15:00', 'Asia/Manila')));
        $this->assertSame('moon', Greeting::icon(Carbon::parse('2026-11-19 19:00', 'Asia/Manila')));
    }

    /**
     * @return array<string, array{bool, bool, string}>
     */
    public static function subtitles(): array
    {
        return [
            'admin overview' => [true, false, 'Thursday, Nov 19, 2026 · Here is how today is going on site.'],
            'admin overview while clocked in' => [true, true, 'Thursday, Nov 19, 2026 · Here is how today is going on site.'],
            'clocked in' => [false, true, 'Thursday, Nov 19, 2026 · You are Clocked In. Keep up the good work!'],
            'not clocked in' => [false, false, 'Thursday, Nov 19, 2026 · Here is your attendance for the last 14 days.'],
        ];
    }

    #[DataProvider('subtitles')]
    public function testTheSubtitleFitsTheDashboard(bool $overview, bool $clockedIn, string $subtitle): void
    {
        $this->assertSame($subtitle, Greeting::subtitle($overview, $clockedIn, Carbon::parse('2026-11-19 09:00', 'Asia/Manila')));
    }

    public function testItGreetsTheUserByFirstName(): void
    {
        $this->assertSame('Good afternoon, Ana!', Greeting::to('Ana', Carbon::parse('2026-11-19 15:00', 'Asia/Manila')));
    }
}
