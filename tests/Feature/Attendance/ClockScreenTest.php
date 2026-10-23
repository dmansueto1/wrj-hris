<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Clock;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClockScreenTest extends TestCase
{
    use RefreshDatabase;

    public function testNotClockedInSeesOngoingProjectsOnlyAndAClockInButton(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->planning()->create(['code' => 'PRJ-002', 'name' => 'Planned Clinic']);
        Project::factory()->onHold()->create(['code' => 'PRJ-003', 'name' => 'Paused Warehouse']);
        Project::factory()->completed()->create(['code' => 'PRJ-004', 'name' => 'Finished School']);

        Livewire::test(Clock::class)
            ->assertSee('PRJ-001')
            ->assertSee('Riverside Townhomes')
            ->assertDontSee('PRJ-002')
            ->assertDontSee('PRJ-003')
            ->assertDontSee('PRJ-004')
            ->assertSeeHtml('data-test="clock-in-button"')
            ->assertDontSeeHtml('data-test="clock-out-button"');
    }

    public function testTheClockPageIsTheHomePageForEveryone(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->get('/')->assertRedirect(route('clock'));
        $this->get(route('clock'))->assertOk()->assertSee('Clock In');
    }

    public function testSelectingAProjectAndClickingClockInCreatesAnOpenSession(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 07:00:00');

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->call('clockIn', 14.5995124, 120.9842195)
            ->assertHasNoErrors();

        $session = Attendance::sole();
        $this->assertSame(Attendance::STATUS_OPEN, $session->status);
        $this->assertSame($worker->id, $session->user_id);
        $this->assertSame($project->id, $session->project_id);
        $this->assertSame('80.00', $session->hourly_rate);
        $this->assertSame('14.5995124', $session->clock_in_lat);
    }

    public function testClockInWithoutLocationStillWorks(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertNull(Attendance::sole()->clock_in_lat);
    }

    public function testAnImpossibleLocationIsIgnored(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->call('clockIn', 999, -999)
            ->assertHasNoErrors();

        $this->assertNull(Attendance::sole()->clock_in_lat);
        $this->assertNull(Attendance::sole()->clock_in_lng);
    }

    public function testClockInWithoutChoosingAProjectShowsAnError(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Clock::class)
            ->call('clockIn')
            ->assertHasErrors('projectId');

        $this->assertSame(0, Attendance::count());
    }

    public function testClockedInSeesClockOutAndClickingItClosesTheSession(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);

        $this->travelTo('2026-03-02 07:00:00');
        app(AttendanceService::class)->clockIn($worker, $project);

        $this->travelTo('2026-03-02 11:30:00');

        Livewire::test(Clock::class)
            ->assertSee('Riverside Townhomes')
            ->assertSee('7:00 AM')
            ->assertSeeHtml('data-test="clock-out-button"')
            ->assertDontSeeHtml('data-test="clock-in-button"')
            ->call('clockOut')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-test="clock-out-summary"')
            ->assertSee('11:30 AM')
            ->assertSee('4.50')
            ->assertSeeHtml('data-test="clock-in-button"');

        $session = Attendance::sole();
        $this->assertSame(Attendance::STATUS_CLOSED, $session->status);
        $this->assertSame('360.00', $session->labor_cost);
    }

    public function testATamperedNonOngoingProjectShowsAnErrorAndCreatesNothing(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $completed = Project::factory()->completed()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $completed->id)
            ->call('clockIn')
            ->assertHasErrors('clock')
            ->assertSee('You can only clock in to an Ongoing project.');

        $this->assertSame(0, Attendance::count());
    }

    public function testAnUnknownProjectIdShowsAnErrorAndCreatesNothing(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Clock::class)
            ->set('projectId', 999999)
            ->call('clockIn')
            ->assertHasErrors('clock');

        $this->assertSame(0, Attendance::count());
    }

    public function testClockingInAgainFromAStaleScreenShowsAFriendlyMessage(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create();

        $screen = Livewire::test(Clock::class)->set('projectId', $project->id);

        // Clocked in from another tab or phone in the meantime.
        app(AttendanceService::class)->clockIn($worker, $project);

        $screen->call('clockIn')
            ->assertHasErrors('clock')
            ->assertSee('You are already clocked in. Clock out first.');

        $this->assertSame(1, Attendance::count());
    }

    public function testClockingOutWhenAlreadyClosedShowsAFriendlyMessage(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Clock::class)
            ->call('clockOut')
            ->assertHasErrors('clock')
            ->assertSee('You are not clocked in.');
    }

    public function testTwoSessionsTodayShowInTheTodayPanelWithTotalNineHours(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $this->actingAs($worker);
        $projectA = Project::factory()->ongoing()->create(['name' => 'Project Alpha']);
        $projectB = Project::factory()->ongoing()->create(['name' => 'Project Bravo']);
        $service = app(AttendanceService::class);

        $this->travelTo('2026-03-02 07:00:00');
        $service->clockIn($worker, $projectA);
        $this->travelTo('2026-03-02 11:30:00');
        $service->clockOut($worker);
        $this->travelTo('2026-03-02 12:30:00');
        $service->clockIn($worker, $projectB);
        $this->travelTo('2026-03-02 17:00:00');
        $service->clockOut($worker);

        Livewire::test(Clock::class)
            ->assertSeeInOrder(['Project Alpha', '7:00 AM', '11:30 AM', '4.50', 'Project Bravo', '12:30 PM', '5:00 PM', '4.50'])
            ->assertSeeHtml('data-test="today-total"')
            ->assertSee('9.00');
    }

    public function testTheTodayPanelShowsOnlyTodaysSessionsForThisUser(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create(['name' => 'Today Project']);
        // Completed, so they are not in the project picker: they could only appear through the Today panel.
        $yesterdayProject = Project::factory()->completed()->create(['name' => 'Yesterday Project']);
        $otherProject = Project::factory()->completed()->create(['name' => 'Someone Else Project']);

        $this->travelTo('2026-03-02 09:00:00');
        Attendance::factory()->for($worker)->for($project)->create(['work_date' => '2026-03-02', 'clock_in_at' => '2026-03-02 07:00:00', 'clock_out_at' => '2026-03-02 11:30:00']);
        Attendance::factory()->for($worker)->for($yesterdayProject)->create(['work_date' => '2026-03-01', 'clock_in_at' => '2026-03-01 07:00:00', 'clock_out_at' => '2026-03-01 11:30:00']);
        Attendance::factory()->for(User::factory()->worker())->for($otherProject)->create(['work_date' => '2026-03-02']);

        Livewire::test(Clock::class)
            ->assertSee('Today Project')
            ->assertDontSee('Yesterday Project')
            ->assertDontSee('Someone Else Project');
    }

    public function testAUserWithoutTheClockPermissionGets403(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('clock'))->assertForbidden();
        Livewire::test(Clock::class)->assertForbidden();
    }

    public function testWithNoOngoingProjectsThereIsNoClockInButton(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        Project::factory()->completed()->create();

        Livewire::test(Clock::class)
            ->assertSee('No Ongoing projects')
            ->assertDontSeeHtml('data-test="clock-in-button"');
    }

    public function testTheTodayPanelSaysAttendanceNotSession(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        app(AttendanceService::class)->clockIn($worker, $project);

        Livewire::test(Clock::class)
            ->assertSee('Clocked In')
            ->assertSee('Clocked In')
            ->assertSee('This ends your attendance on Riverside Townhomes.')
            ->assertDontSeeText('session'); // visible text only; internal keys may still say session
    }

    public function testWithNoAttendanceTodayThePanelSaysSo(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Clock::class)->assertSee('No attendance yet today.');
    }

    public function testProjectsArePickedFromADropdownOfOngoingProjects(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $ongoing = Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->onHold()->create(['code' => 'PRJ-009', 'name' => 'Paused Warehouse']);

        Livewire::test(Clock::class)
            ->assertSet('projectId', '')
            ->assertSeeHtml('data-test="clock-project-select"')
            ->assertSeeHtml('value="'.$ongoing->id.'"')
            ->assertSeeHtml('<option value="" disabled selected class="placeholder">Select project</option>')
            ->assertSee('PRJ-001 · Riverside Townhomes')
            ->assertDontSee('Paused Warehouse');
    }

    public function testTheSelectProjectPlaceholderMatchesTheEmptyValueSoTheFirstProjectIsNotShownAsChosen(): void
    {
        // A null value matches no <option>, so the browser shows the first project while
        // the component has none, and the Clock In button stays disabled.
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->assertSet('projectId', '')
            ->set('projectId', (string) $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertSame($project->id, $worker->attendances()->sole()->project_id);
    }

    public function testWhileClockedInTheWorkedTimeRunsToTheSecond(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $this->travelTo('2026-03-02 07:00:00');
        app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());
        $this->travelTo('2026-03-02 09:05:09');

        Livewire::test(Clock::class)
            ->assertSeeHtml('data-test="clock-running-time"')
            ->assertSeeHtml('runningTime('.now()->setTime(7, 0)->getTimestamp() * 1000 .')')
            ->assertSee('Worked time')
            ->assertSee('2h 05m 09s')
            ->assertSee('Clocked In at 7:00 AM');
    }

    public function testTheTodayPanelShowsWorkedTimeWithTheUnitAndStatus(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $this->travelTo('2026-03-02 13:00:00');
        Attendance::factory()->for($worker)->create(['work_date' => '2026-03-02', 'clock_in_at' => '2026-03-02 07:00:00', 'clock_out_at' => '2026-03-02 11:30:00']);

        Livewire::test(Clock::class)
            ->assertSeeHtml('data-test="worked-hours">4.50 h')
            ->assertSeeHtml('data-test="status-closed"')
            ->assertSeeHtml('data-test="today-total">4.50</span>');
    }

    public function testTheTodayPanelShowsOnlyTheStatusForTheAttendanceStillClockedIn(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $this->travelTo('2026-03-02 07:00:00');
        app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());
        $this->travelTo('2026-03-02 09:05:09');

        // One running time only: the big Worked time card, not the Today list.
        $html = Livewire::test(Clock::class)->html();
        $this->assertSame(1, substr_count($html, 'runningTime('));
        $this->assertStringNotContainsString('data-test="running-time"', $html);
        $this->assertStringContainsString('data-test="status-open"', $html);
    }
}
