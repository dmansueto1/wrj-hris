<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Mine;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RestDayStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 2026-10-21. Sunday 2026-10-18 is the rest day of the factory shifts.
        $this->travelTo('2026-10-21 15:00:00');
    }

    public function testTheDailyTabShowsRestDayForAShiftEmployeeWithoutAttendanceOnTheirRestDay(): void
    {
        $this->actingAs(User::factory()->admin()->create(['last_name' => 'Zamora']));
        $resting = User::factory()->worker()->oneShift()->create(['last_name' => 'Abad']);
        $working = User::factory()->worker()->twoShifts()->create(['last_name' => 'Bautista']);
        $flexible = User::factory()->worker()->create(['last_name' => 'Cruz']);
        Attendance::factory()->for($working)->onDate('2026-10-18')->create();

        $component = Livewire::test(Index::class)
            ->set('date', '2026-10-18')
            ->assertSee('Rest Day')
            ->assertSeeHtml('data-test="presence-rest-day"')
            ->assertSeeHtml('data-test="presence-present"')
            ->assertSeeHtml('data-test="presence-absent"');

        $rows = collect($component->instance()->daily->items())->keyBy(fn (array $row) => $row['user']->id);
        $this->assertTrue($rows[$resting->id]['rest_day']);
        $this->assertFalse($rows[$resting->id]['present']);
        // Attendance on a rest day is Present, not Rest Day.
        $this->assertTrue($rows[$working->id]['present']);
        $this->assertFalse($rows[$working->id]['rest_day']);
        // Flexible employees have no rest day.
        $this->assertFalse($rows[$flexible->id]['rest_day']);

        // The resting employee is not Absent: only Cruz and the admin are.
        $this->assertSame(['employees' => 4, 'present' => 1, 'absent' => 2], array_intersect_key(
            $component->instance()->dailySummary,
            array_flip(['employees', 'present', 'absent']),
        ));
    }

    public function testAWorkingDayWithoutAttendanceIsStillAbsent(): void
    {
        $this->actingAs(User::factory()->admin()->oneShift()->create());

        $component = Livewire::test(Index::class)
            ->set('date', '2026-10-19')
            ->assertSeeHtml('data-test="presence-absent"')
            ->assertDontSeeHtml('data-test="presence-rest-day"');

        $this->assertSame(1, $component->instance()->dailySummary['absent']);
    }

    public function testTheRestDayBadgeIsBlueLikeTheButtons(): void
    {
        $this->actingAs(User::factory()->admin()->oneShift()->create());

        Livewire::test(Index::class)
            ->set('date', '2026-10-18')
            ->assertSeeHtml('bg-info-soft text-info');
    }

    public function testTheByEmployeeTabShowsRestDaysAndDoesNotCountThemAsAbsent(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->oneShift()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-13')->create();

        $component = Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $worker->id)
            ->set('from', '2026-10-12')
            ->set('to', '2026-10-18')
            ->assertSeeHtml('data-test="presence-rest-day"');

        $days = collect($component->instance()->employeeDays->items())->keyBy(fn (array $day) => $day['date']->toDateString());
        $this->assertTrue($days['2026-10-18']['rest_day']);
        $this->assertFalse($days['2026-10-13']['rest_day']);
        $this->assertFalse($days['2026-10-14']['rest_day']);

        $summary = $component->instance()->employeeSummary;
        $this->assertSame(1, $summary['present_days']);
        $this->assertSame(5, $summary['absent_days']);
    }

    public function testMyAttendanceShowsPresentOnARestDayWithAttendance(): void
    {
        $worker = User::factory()->worker()->oneShift()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-18')->create();
        $this->actingAs($worker);

        $component = Livewire::test(Mine::class)
            ->set('from', '2026-10-12')
            ->set('to', '2026-10-18')
            ->assertDontSeeHtml('data-test="presence-rest-day"');

        $days = collect($component->instance()->days->items())->keyBy(fn (array $day) => $day['date']->toDateString());
        $this->assertTrue($days['2026-10-18']['present']);
        $this->assertFalse($days['2026-10-18']['rest_day']);
        $this->assertSame(['present_days' => 1, 'absent_days' => 6], array_intersect_key(
            $component->instance()->totals,
            array_flip(['present_days', 'absent_days']),
        ));
    }

    public function testMyAttendanceShowsRestDayOnARestDayWithoutAttendance(): void
    {
        $this->actingAs(User::factory()->worker()->oneShift()->create());

        $component = Livewire::test(Mine::class)
            ->set('from', '2026-10-12')
            ->set('to', '2026-10-18')
            ->assertSee('Rest Day')
            ->assertSeeHtml('data-test="presence-rest-day"');

        $this->assertSame(6, $component->instance()->totals['absent_days']);
    }
}
