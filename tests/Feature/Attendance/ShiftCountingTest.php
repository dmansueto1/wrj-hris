<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Clock;
use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Mine;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\UserShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftCountingTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->ongoing()->create();
    }

    /**
     * Clock in and out at the given times (Asia/Manila), as the clock screens do.
     */
    private function work(User $user, string $in, string $out): Attendance
    {
        $service = app(AttendanceService::class);

        $this->travelTo($in);
        $service->clockIn($user, $this->project);
        $this->travelTo($out);

        return $service->clockOut($user);
    }

    public function testOnlyTheMinutesInsideTheShiftAreCountedAndCosted(): void
    {
        // Monday 2026-10-19: 08:00–12:00 and 13:00–17:00 at ₱80.00/hr.
        $worker = User::factory()->worker()->twoShifts()->create();

        $attendance = $this->work($worker, '2026-10-19 07:50:00', '2026-10-19 12:24:00')->fresh();

        $this->assertSame(274, $attendance->clocked_minutes);
        $this->assertSame(240, $attendance->minutes_worked);
        $this->assertSame('4.00', $attendance->hours_worked);
        $this->assertSame('320.00', $attendance->labor_cost);
        // MySQL's JSON column keeps its own key order, so compare the values, not the order.
        $this->assertEquals([
            ['start' => '2026-10-19 08:00', 'end' => '2026-10-19 12:00'],
            ['start' => '2026-10-19 13:00', 'end' => '2026-10-19 17:00'],
        ], $attendance->shift_windows);
        $this->assertSame(34, $attendance->uncountedMinutes());
        $this->assertFalse($attendance->isOutsideShift());
    }

    public function testTheShiftSavedAtClockInIsUsedEvenIfItChangesBeforeClockOut(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $service = app(AttendanceService::class);

        $this->travelTo('2026-10-19 07:50:00');
        $service->clockIn($worker, $this->project);

        // The admin moves Monday to 09:00–12:00 while the worker is clocked in.
        $week = app(UserShiftService::class)->schedule($worker);
        $week[1]['first_in'] = '09:00';
        app(UserShiftService::class)->update($worker, User::SHIFT_TWO, $week, User::factory()->admin()->create());

        $this->travelTo('2026-10-19 12:24:00');
        $attendance = $service->clockOut($worker)->fresh();

        $this->assertSame(240, $attendance->minutes_worked);
        $this->assertSame('320.00', $attendance->labor_cost);
    }

    public function testALateArrivalOnANightShiftCountsTheNightWindow(): void
    {
        // Monday 22:00 – Tuesday 06:00; the worker only arrives at 00:10 on Tuesday.
        $worker = User::factory()->worker()->oneShift('22:00', '06:00')->create();

        $attendance = $this->work($worker, '2026-10-20 00:10:00', '2026-10-20 06:00:00')->fresh();

        $this->assertSame(350, $attendance->minutes_worked);
        $this->assertSame('5.83', $attendance->hours_worked);
        $this->assertSame('466.67', $attendance->labor_cost);
    }

    public function testAFlexibleUserStillCountsEveryMinute(): void
    {
        $worker = User::factory()->worker()->create();

        $attendance = $this->work($worker, '2026-10-19 07:00:00', '2026-10-19 11:30:00')->fresh();

        $this->assertNull($attendance->shift_windows);
        $this->assertSame(270, $attendance->clocked_minutes);
        $this->assertSame(270, $attendance->minutes_worked);
        $this->assertSame('4.50', $attendance->hours_worked);
        $this->assertSame('360.00', $attendance->labor_cost);
        $this->assertSame(0, $attendance->uncountedMinutes());
    }

    public function testARestDayAttendanceCountsNothingAndShowsOutsideShift(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create(['first_name' => 'Ana', 'last_name' => 'Abad']);

        // Sunday 2026-10-25 is the rest day.
        $attendance = $this->work($worker, '2026-10-25 08:00:00', '2026-10-25 12:00:00')->fresh();

        $this->assertSame([], $attendance->shift_windows);
        $this->assertSame(0, $attendance->minutes_worked);
        $this->assertSame('0.00', $attendance->hours_worked);
        $this->assertSame('0.00', $attendance->labor_cost);
        $this->assertTrue($attendance->isOutsideShift());

        $this->actingAs(User::factory()->admin()->create());
        $html = Livewire::test(Index::class)
            ->set('date', '2026-10-25')
            ->assertSee('Outside shift')
            ->assertSeeHtml('data-test="outside-shift"')
            ->html();

        // One item in the Remarks cell: the badge, with the clocked time in its tooltip.
        preg_match('/data-test="remarks"[\s\S]*?<\/div>/', $html, $remarks);
        $this->assertStringContainsString('data-test="outside-shift"', $remarks[0]);
        $this->assertStringContainsString('title="Clocked 4h 00m. Only time inside the shift (Rest day) counts."', $remarks[0]);
        $this->assertStringNotContainsString('data-test="clocked-time"', $remarks[0]);
    }

    public function testTheAttendancePagesShowCountedHoursWithTheClockedTime(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->work($worker, '2026-10-19 07:50:00', '2026-10-19 12:24:00');
        $this->travelTo('2026-10-19 15:00:00');

        $this->actingAs(User::factory()->admin()->create());
        $index = Livewire::test(Index::class)
            ->set('date', '2026-10-19')
            ->assertSeeHtml('data-test="worked-hours">4.00 h')
            ->assertSee('Clocked 4h 34m')
            ->assertSee('Only time inside the shift (8:00 AM–12:00 PM, 1:00 PM–5:00 PM) counts.')
            ->assertSee('7:50 AM')
            ->assertSee('12:24 PM')
            ->assertDontSee('Outside shift');

        // The All Attendance totals use the counted labor cost.
        $this->assertSame('320.00', $index->instance()->dailySummary['labor_cost']);

        $this->actingAs($worker);
        Livewire::test(Mine::class)
            ->assertSeeHtml('data-test="worked-hours">4.00 h')
            ->assertSee('Clocked 4h 34m');

        foreach ([route('attendance.index', ['date' => '2026-10-19']), route('attendance.mine')] as $url) {
            $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()->assertDontSeeText('session');
        }
    }

    public function testTheClockPageShowsTodaysShift(): void
    {
        $this->actingAs(User::factory()->worker()->twoShifts()->create());

        $this->travelTo('2026-10-19 07:00:00'); // Monday
        Livewire::test(Clock::class)->assertSee('Your shift today: 8:00 AM–12:00 PM, 1:00 PM–5:00 PM');

        $this->travelTo('2026-10-25 07:00:00'); // Sunday
        Livewire::test(Clock::class)->assertSee('Your shift today: Rest day');

        $this->actingAs(User::factory()->worker()->create());
        Livewire::test(Clock::class)->assertDontSee('Your shift today');
    }
}
