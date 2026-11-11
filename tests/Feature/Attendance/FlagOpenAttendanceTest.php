<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Clock;
use App\Livewire\Attendance\Correction;
use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Kiosk;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class FlagOpenAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICE = 'You have an attendance from Oct 19, 2026 that was not clocked out. Your admin will correct it.';

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 2026-10-19, 7:00 AM.
        $this->travelTo('2026-10-19 07:00:00');
    }

    // ---- The command ------------------------------------------------------------------------

    public function testAnAttendanceOpenThirteenHoursIsFlaggedAndOneOpenElevenHoursIsNot(): void
    {
        $attendance = $this->openAttendance();

        $this->travel(11)->hours();
        $this->artisan('attendance:flag-open')->assertSuccessful();
        $this->assertSame(Attendance::STATUS_OPEN, $attendance->refresh()->status);

        $this->travel(2)->hours();
        $this->artisan('attendance:flag-open')->expectsOutputToContain('1 attendance record flagged')->assertSuccessful();
        $this->assertSame(Attendance::STATUS_FLAGGED, $attendance->refresh()->status);
    }

    public function testExactlyTwelveHoursIsNotFlaggedYet(): void
    {
        $attendance = $this->openAttendance();

        $this->travel(12)->hours();
        $this->artisan('attendance:flag-open');

        $this->assertSame(Attendance::STATUS_OPEN, $attendance->refresh()->status);
    }

    public function testOnlyOpenAttendanceIsFlagged(): void
    {
        $closed = Attendance::factory()->onDate('2026-10-18')->create();
        $void = Attendance::factory()->onDate('2026-10-18')->open()->void()->create();
        $open = $this->openAttendance();

        $this->travel(13)->hours();
        $this->artisan('attendance:flag-open');

        $this->assertSame(Attendance::STATUS_CLOSED, $closed->refresh()->status);
        $this->assertSame(Attendance::STATUS_VOID, $void->refresh()->status);
        $this->assertSame(Attendance::STATUS_FLAGGED, $open->refresh()->status);
    }

    public function testRunningTheCommandTwiceChangesNothingTheSecondTime(): void
    {
        $attendance = $this->openAttendance();
        $this->travel(13)->hours();

        $this->artisan('attendance:flag-open')->expectsOutputToContain('1 attendance record flagged');
        $flaggedAt = $attendance->refresh()->updated_at;

        $this->travel(1)->hours();
        $this->artisan('attendance:flag-open')->expectsOutputToContain('0 attendance records flagged');

        $attendance->refresh();
        $this->assertSame(Attendance::STATUS_FLAGGED, $attendance->status);
        $this->assertTrue($flaggedAt->equalTo($attendance->updated_at));
    }

    public function testTheSchedulerRunsTheCommandHourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'attendance:flag-open'));

        $this->assertNotNull($event, 'attendance:flag-open is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression);
    }

    // ---- Clocking in again ------------------------------------------------------------------

    public function testAUserWithAFlaggedAttendanceCanClockInAgain(): void
    {
        $worker = User::factory()->worker()->create();
        $flagged = $this->openAttendance($worker);
        $this->travel(13)->hours();
        $this->artisan('attendance:flag-open');

        $project = Project::factory()->ongoing()->create();
        $new = app(AttendanceService::class)->clockIn($worker, $project);

        $this->assertSame(Attendance::STATUS_OPEN, $new->status);
        $this->assertSame(Attendance::STATUS_FLAGGED, $flagged->refresh()->status);
        $this->assertTrue(app(AttendanceService::class)->openSessionFor($worker)->is($new));
    }

    public function testTheClockScreenLetsAUserWithAFlaggedAttendanceClockIn(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->flagged()->create();
        $project = Project::factory()->ongoing()->create();
        $this->travel(1)->days();
        $this->actingAs($worker);

        Livewire::test(Clock::class)
            ->assertSeeHtml('data-test="clock-in-button"')
            ->set('projectId', (string) $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::query()->where('status', Attendance::STATUS_OPEN)->count());
    }

    public function testTheKioskLetsAWorkerWithAFlaggedAttendanceClockIn(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->flagged()->create();
        $project = Project::factory()->ongoing()->create();
        $this->travel(1)->days();

        $this->verify($worker)
            ->assertSeeHtml('data-test="kiosk-clock-in-button"')
            ->set('projectId', (string) $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::query()->where('status', Attendance::STATUS_OPEN)->count());
    }

    // ---- Notices ----------------------------------------------------------------------------

    public function testTheClockScreenShowsTheNoticeAndNeverSaysSession(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->flagged()->create();
        $this->travel(1)->days();
        $this->actingAs($worker);

        Livewire::test(Clock::class)
            ->assertSee(self::NOTICE)
            ->assertSeeHtml('data-test="flagged-notice"')
            ->assertDontSeeText('session')
            ->assertDontSeeText('Session');

        $this->get(route('clock'))->assertOk()->assertSee(self::NOTICE)->assertDontSeeText('session');
    }

    public function testNoNoticeWithoutAFlaggedAttendance(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Clock::class)->assertDontSeeHtml('data-test="flagged-notice"');
    }

    public function testTheKioskShowsTheNoticeInStepTwo(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->flagged()->create();
        $this->travel(1)->days();

        Livewire::test(Kiosk::class)->assertDontSee(self::NOTICE);

        $this->verify($worker)
            ->assertSee(self::NOTICE)
            ->assertSeeHtml('data-test="kiosk-flagged-notice"')
            ->assertDontSeeText('session');
    }

    // ---- Needs correction on All Attendance -------------------------------------------------

    public function testAllAttendanceLinksToTheFlaggedRecordsWithTheirCount(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Abad']);
        $ben = User::factory()->worker()->create(['first_name' => 'Ben', 'last_name' => 'Bautista']);
        $flaggedA = Attendance::factory()->for($ana)->onDate('2026-10-15')->flagged()->create();
        $flaggedB = Attendance::factory()->for($ben)->onDate('2026-10-17')->flagged()->create();
        $closed = Attendance::factory()->for($ana)->onDate('2026-10-19')->create();

        $component = Livewire::test(Index::class)
            ->assertSee('Needs correction (2)')
            ->assertSeeHtml('data-test="needs-correction-link"')
            ->call('showNeedsCorrection')
            ->assertSet('needsCorrection', true)
            ->assertSeeHtml('data-test="flagged-row-'.$flaggedA->id.'"')
            ->assertSeeHtml('data-test="flagged-row-'.$flaggedB->id.'"')
            ->assertDontSeeHtml('data-test="flagged-row-'.$closed->id.'"')
            ->assertSee('Abad, Ana')
            ->assertSee('Bautista, Ben')
            ->assertSeeHtml('data-test="correct-attendance-'.$flaggedA->id.'"');

        $this->assertSame([$flaggedA->id, $flaggedB->id], collect($component->instance()->flagged->items())->pluck('id')->all());

        $component->call('clearFilters')->assertSet('needsCorrection', false);
    }

    public function testStaffSeeTheFlaggedRecordsButCannotCorrectThem(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $flagged = Attendance::factory()->onDate('2026-10-15')->flagged()->create();

        Livewire::withQueryParams(['needs_correction' => '1'])->test(Index::class)
            ->assertSet('needsCorrection', true)
            ->assertSeeHtml('data-test="flagged-row-'.$flagged->id.'"')
            ->assertDontSeeHtml('data-test="correct-attendance-'.$flagged->id.'"');
    }

    public function testCorrectingAFlaggedRecordRemovesItFromTheList(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $flagged = Attendance::factory()->onDate('2026-10-15')->flagged()->create();

        Livewire::test(Correction::class)
            ->call('open', $flagged->id)
            ->set('clock_out', '2026-10-15T11:30')
            ->set('reason', 'Forgot to clock out.')
            ->set('password', 'password')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(Index::class)
            ->assertDontSee('Needs correction (')
            ->set('needsCorrection', true)
            ->assertDontSeeHtml('data-test="flagged-row-'.$flagged->id.'"')
            ->assertSee('Nothing needs correction.');
    }

    private function openAttendance(?User $user = null): Attendance
    {
        return Attendance::factory()
            ->for($user ?? User::factory()->worker()->create())
            ->open()
            ->create(['work_date' => '2026-10-19', 'clock_in_at' => '2026-10-19 07:00:00']);
    }

    private function verify(User $worker): Testable
    {
        return Livewire::test(Kiosk::class)
            ->set('username', $worker->username)
            ->set('password', 'password')
            ->call('verify');
    }
}
