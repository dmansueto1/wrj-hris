<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\KioskDuties;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\KioskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KioskDutiesTest extends TestCase
{
    use RefreshDatabase;

    public function testRecentDutiesShowsTheTenNewestClockEventsFromTodayOnly(): void
    {
        $this->travelTo('2026-03-02 18:00:00');
        $project = Project::factory()->ongoing()->create();

        // 6 duties today, each clocked in and out = 12 clock events, 07:00 … 12:30.
        foreach (range(0, 5) as $i) {
            $in = now()->setTime(7, 0)->addMinutes($i * 60);
            Attendance::factory()->for(User::factory()->worker()->create(['first_name' => "Worker{$i}", 'last_name' => 'Test']))->for($project)->create([
                'work_date' => '2026-03-02',
                'clock_in_at' => $in,
                'clock_out_at' => $in->addMinutes(30),
            ]);
        }

        // 1 duty yesterday = 2 clock events that must not show.
        Attendance::factory()->for(User::factory()->worker()->create(['first_name' => 'Yesterday', 'last_name' => 'Person']))->for($project)->create([
            'work_date' => '2026-03-01',
            'clock_in_at' => '2026-03-01 07:00:00',
            'clock_out_at' => '2026-03-01 11:30:00',
        ]);

        $rows = app(KioskService::class)->recentDuties(10);

        $this->assertCount(10, $rows);
        $this->assertSame(['Worker5 Test', 'Clocked Out', '12:30 PM'], [$rows[0]['name'], $rows[0]['type'], $rows[0]['time']]);
        $this->assertSame(['Worker5 Test', 'Clocked In', '12:00 PM'], [$rows[1]['name'], $rows[1]['type'], $rows[1]['time']]);
        $this->assertSame(['Worker1 Test', 'Clocked In', '8:00 AM'], [$rows[9]['name'], $rows[9]['type'], $rows[9]['time']]);
        $this->assertNotContains('Yesterday P.', array_column($rows->all(), 'name'));
    }

    public function testAnOpenAttendanceGivesOnlyAClockedInRow(): void
    {
        $this->travelTo('2026-03-02 09:00:00');
        Attendance::factory()->open()->for(User::factory()->worker()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']))->create([
            'work_date' => '2026-03-02',
            'clock_in_at' => '2026-03-02 07:02:00',
        ]);

        $rows = app(KioskService::class)->recentDuties(10);

        $this->assertCount(1, $rows);
        $this->assertSame(['Juan Dela Cruz', 'Clocked In', '7:02 AM'], [$rows[0]['name'], $rows[0]['type'], $rows[0]['time']]);
    }

    public function testAnOvernightAttendanceClockedOutThisMorningShowsItsClockedOutRow(): void
    {
        $this->travelTo('2026-03-03 06:30:00');
        Attendance::factory()->for(User::factory()->worker()->create(['first_name' => 'Night', 'last_name' => 'Shift']))->create([
            'work_date' => '2026-03-02',
            'clock_in_at' => '2026-03-02 22:00:00',
            'clock_out_at' => '2026-03-03 06:00:00',
        ]);

        $rows = app(KioskService::class)->recentDuties(10);

        $this->assertCount(1, $rows);
        $this->assertSame(['Night Shift', 'Clocked Out', '6:00 AM'], [$rows[0]['name'], $rows[0]['type'], $rows[0]['time']]);
    }

    public function testVoidAttendanceIsLeftOut(): void
    {
        $this->travelTo('2026-03-02 12:00:00');
        Attendance::factory()->create(['work_date' => '2026-03-02', 'status' => Attendance::STATUS_VOID]);

        $this->assertCount(0, app(KioskService::class)->recentDuties(10));
    }

    public function testTheListShowsNamesProjectsAndTimesButNoMoneyOrEmployeeNumbers(): void
    {
        $this->travelTo('2026-03-02 12:00:00');
        $worker = User::factory()->worker()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'employee_no' => 'EMP-0042']);
        Attendance::factory()->for($worker)->for(Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']))->create([
            'work_date' => '2026-03-02',
        ]);

        Livewire::test(KioskDuties::class)
            ->assertSee("Today's attendance")
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Riverside Townhomes')
            ->assertSee('Clocked Out')
            ->assertDontSee('EMP-0042')
            ->assertDontSee('₱')
            ->assertDontSee('360.00');
    }

    public function testAnEmptyDayShowsTheEmptyState(): void
    {
        Livewire::test(KioskDuties::class)->assertSee('No one has clocked in yet today.');
    }

    public function testTheLiveCounterShowsHowManyAreClockedInNow(): void
    {
        Attendance::factory()->open()->count(3)->create();
        Attendance::factory()->create();

        $this->assertSame(3, app(KioskService::class)->onDutyCount());

        Livewire::test(KioskDuties::class)
            ->assertSeeHtml('data-test="kiosk-on-duty-count"')
            ->assertSee('3 clocked in now');
    }
}
