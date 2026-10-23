<?php

namespace Tests\Feature\Attendance;

use App\Exceptions\Attendance\AlreadyClockedInException;
use App\Exceptions\Attendance\InactiveUserException;
use App\Exceptions\Attendance\NotClockedInException;
use App\Exceptions\Attendance\ProjectNotOngoingException;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\UserRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendance = app(AttendanceService::class);
    }

    public function testClockInAtSevenAndOutAtElevenThirtyCostsThreeHundredSixty(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $project = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 07:00:00');
        $this->attendance->clockIn($worker, $project);

        $this->travelTo('2026-03-02 11:30:00');
        $session = $this->attendance->clockOut($worker);

        $this->assertSame(270, $session->minutes_worked);
        $this->assertSame('4.50', $session->hours_worked);
        $this->assertSame('360.00', $session->labor_cost);
        $this->assertSame(Attendance::STATUS_CLOSED, $session->status);
        $this->assertSame('2026-03-02 11:30:00', $session->clock_out_at->format('Y-m-d H:i:s'));
    }

    public function testClockInSavesTheTimeProjectRateAndWorkDate(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '85.50']);
        $project = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 07:00:45');
        $session = $this->attendance->clockIn($worker, $project, 14.5995124, 120.9842195);

        $session->refresh();
        $this->assertSame($worker->id, $session->user_id);
        $this->assertSame($project->id, $session->project_id);
        $this->assertSame('2026-03-02', $session->work_date->toDateString());
        $this->assertSame('2026-03-02 07:00:00', $session->clock_in_at->format('Y-m-d H:i:s'), 'Seconds are set to 0.');
        $this->assertNull($session->clock_out_at);
        $this->assertSame('85.50', $session->hourly_rate);
        $this->assertSame(Attendance::STATUS_OPEN, $session->status);
        $this->assertSame('14.5995124', $session->clock_in_lat);
        $this->assertSame('120.9842195', $session->clock_in_lng);
        $this->assertNull($session->labor_cost);
    }

    public function testClockingInTwiceWithoutClockingOutThrowsAlreadyClockedIn(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->attendance->clockIn($worker, $project);

        $this->expectException(AlreadyClockedInException::class);

        $this->attendance->clockIn($worker, $project);
    }

    public function testTwoQuickClockInsCreateOnlyOneSession(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->attendance->clockIn($worker, $project);

        try {
            $this->attendance->clockIn($worker, $project);
        } catch (AlreadyClockedInException) {
            // expected: the double tap is rejected
        }

        $this->assertSame(1, Attendance::where('user_id', $worker->id)->count());
    }

    public function testClockingOutWithNoOpenSessionThrowsNotClockedIn(): void
    {
        $worker = User::factory()->worker()->create();

        $this->expectException(NotClockedInException::class);

        $this->attendance->clockOut($worker);
    }

    public function testClockingInToACompletedProjectThrowsProjectNotOngoing(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->completed()->create();

        $this->expectException(ProjectNotOngoingException::class);

        $this->attendance->clockIn($worker, $project);
    }

    public function testClockingInToAnArchivedProjectIsRejected(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();
        $project->delete();

        $this->expectException(ProjectNotOngoingException::class);

        $this->attendance->clockIn($worker, $project);
    }

    public function testAnInactiveUserCannotClockIn(): void
    {
        $worker = User::factory()->worker()->inactive()->create();
        $project = Project::factory()->ongoing()->create();

        $this->expectException(InactiveUserException::class);

        $this->attendance->clockIn($worker, $project);
    }

    public function testMorningOnProjectAAndAfternoonOnProjectBChargeEachProjectThreeHundredSixty(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $projectA = Project::factory()->ongoing()->create();
        $projectB = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 07:00:00');
        $this->attendance->clockIn($worker, $projectA);
        $this->travelTo('2026-03-02 11:30:00');
        $this->attendance->clockOut($worker);

        $this->travelTo('2026-03-02 12:30:00');
        $this->attendance->clockIn($worker, $projectB);
        $this->travelTo('2026-03-02 17:00:00');
        $this->attendance->clockOut($worker);

        $this->assertSame('360.00', $projectA->laborTotal());
        $this->assertSame('360.00', $projectB->laborTotal());
        $this->assertSame(2, Attendance::where('user_id', $worker->id)->count());
        $this->assertSame(540, (int) Attendance::where('user_id', $worker->id)->sum('minutes_worked'), 'The lunch hour is not counted.');
    }

    public function testARateChangedWhileClockedInDoesNotChangeTheOpenSession(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $project = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 07:00:00');
        $this->attendance->clockIn($worker, $project);

        app(UserRateService::class)->changeRate($worker, '90.00', $admin);

        $this->travelTo('2026-03-02 11:30:00');
        $session = $this->attendance->clockOut($worker->fresh());

        $this->assertSame('80.00', $session->hourly_rate);
        $this->assertSame('360.00', $session->labor_cost);
    }

    public function testAnOvernightSessionCountsFourHundredEightyMinutesOnTheClockInDate(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '95.50']);
        $project = Project::factory()->ongoing()->create();

        $this->travelTo('2026-03-02 22:00:00');
        $this->attendance->clockIn($worker, $project);

        $this->travelTo('2026-03-03 06:00:00');
        $session = $this->attendance->clockOut($worker);

        $this->assertSame(480, $session->minutes_worked);
        $this->assertSame('8.00', $session->hours_worked);
        $this->assertSame('764.00', $session->labor_cost);
        $this->assertSame('2026-03-02', $session->work_date->toDateString());
    }

    public function testClockOutSavesTheLocation(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->attendance->clockIn($worker, $project);
        $session = $this->attendance->clockOut($worker, 14.55, 121.02)->refresh();

        $this->assertSame('14.5500000', $session->clock_out_lat);
        $this->assertSame('121.0200000', $session->clock_out_lng);
    }

    public function testOnlyClosedAndAdjustedSessionsCountTowardLaborTotal(): void
    {
        $project = Project::factory()->ongoing()->create();
        $make = fn (string $status, ?string $cost) => Attendance::factory()->for($project)->create(['status' => $status, 'labor_cost' => $cost]);

        $make(Attendance::STATUS_CLOSED, '360.00');
        $make(Attendance::STATUS_ADJUSTED, '100.50');
        $make(Attendance::STATUS_OPEN, null);
        $make(Attendance::STATUS_FLAGGED, null);
        $make(Attendance::STATUS_VOID, '999.99');

        $this->assertSame('460.50', $project->laborTotal());
        $this->assertSame(2, Attendance::counted()->count());
        $this->assertSame(1, Attendance::open()->count());
    }

    public function testLaborTotalIsZeroWithNoSessions(): void
    {
        $this->assertSame('0.00', Project::factory()->create()->laborTotal());
    }

    public function testAfterClockingOutTheWorkerCanClockInAgain(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->attendance->clockIn($worker, $project);
        $this->attendance->clockOut($worker);
        $this->attendance->clockIn($worker, $project);

        $this->assertSame(1, Attendance::open()->where('user_id', $worker->id)->count());
    }

    public function testTheModelRelations(): void
    {
        $admin = User::factory()->admin()->create();
        $session = Attendance::factory()->create(['adjusted_by' => $admin->id]);

        $this->assertInstanceOf(User::class, $session->user);
        $this->assertInstanceOf(Project::class, $session->project);
        $this->assertTrue($session->adjustedBy->is($admin));
    }

    public function testTodayTotalIsTheSumOfTheHoursShown(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->count(3)->for($worker)->onDate(now()->toDateString())->create([
            'minutes_worked' => 260,
            'hours_worked' => '4.33',
            'labor_cost' => '346.67',
        ]);

        // 4.33 + 4.33 + 4.33, the same as the My Attendance day total (not 780 minutes = 13.00).
        $this->assertSame('12.99', app(AttendanceService::class)->today($worker)['total_hours']);
    }
}
