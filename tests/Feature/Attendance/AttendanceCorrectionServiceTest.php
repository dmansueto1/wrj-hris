<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceCorrectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Forgot to clock out at lunch.';

    private AttendanceCorrectionService $corrections;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 18:00:00');
        $this->corrections = app(AttendanceCorrectionService::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function testSettingTheClockOutOfAFlaggedAttendanceComputesLaborCost(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create(['hourly_rate' => '80.00']);

        $corrected = $this->corrections->setClockOut($attendance, Carbon::parse('2026-10-21 11:30'), self::REASON, $this->admin)->refresh();

        $this->assertSame('2026-10-21 07:00', $corrected->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-21 11:30', $corrected->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame(270, $corrected->clocked_minutes);
        $this->assertSame(270, $corrected->minutes_worked);
        $this->assertSame('4.50', $corrected->hours_worked);
        $this->assertSame('360.00', $corrected->labor_cost);
        $this->assertSame(Attendance::STATUS_ADJUSTED, $corrected->status);
        $this->assertSame($this->admin->id, $corrected->adjusted_by);
        $this->assertSame(self::REASON, $corrected->adjustment_reason);
    }

    public function testTheOriginalIsArchivedUnchangedAndTheCorrectionPointsToIt(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create([
            'clock_in_photo_path' => 'attendance/in.jpg',
            'clock_in_lat' => '14.5995000',
        ]);

        $corrected = $this->corrections->setClockOut($attendance, Carbon::parse('2026-10-21 11:30'), self::REASON, $this->admin);

        $this->assertNotSame($attendance->id, $corrected->id);
        $this->assertSame($attendance->id, $corrected->corrected_from_id);
        $this->assertTrue($corrected->correctedFrom->is($attendance));
        $this->assertSame('attendance/in.jpg', $corrected->clock_in_photo_path);
        $this->assertSame('14.5995000', $corrected->clock_in_lat);

        // The original: archived, values as they were.
        $this->assertSoftDeleted($attendance);
        $original = Attendance::withTrashed()->findOrFail($attendance->id);
        $this->assertSame(Attendance::STATUS_FLAGGED, $original->status);
        $this->assertNull($original->clock_out_at);
        $this->assertNull($original->labor_cost);
        $this->assertNull($original->adjusted_by);

        // Only the corrected record is current.
        $this->assertSame([$corrected->id], Attendance::query()->pluck('id')->all());
        $this->assertSame(2, Attendance::withTrashed()->count());
    }

    public function testTheSavedHourlyRateIsUsedNotTheUsersCurrentRate(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '120.00']);
        $attendance = Attendance::factory()->for($worker)->onDate('2026-10-21')->open()->create(['hourly_rate' => '80.00']);

        $corrected = $this->corrections->setClockOut($attendance, Carbon::parse('2026-10-21 11:30'), self::REASON, $this->admin);

        $this->assertSame('80.00', $corrected->refresh()->hourly_rate);
        $this->assertSame('360.00', $corrected->labor_cost);
    }

    public function testOnlyTheMinutesInsideTheSavedShiftCount(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create([
            'clock_in_at' => '2026-10-21 07:50:00',
            'shift_windows' => [['start' => '2026-10-21 08:00', 'end' => '2026-10-21 12:00']],
        ]);

        $corrected = $this->corrections->setClockOut($attendance, Carbon::parse('2026-10-21 12:24'), self::REASON, $this->admin)->refresh();

        $this->assertSame(274, $corrected->clocked_minutes);
        $this->assertSame(240, $corrected->minutes_worked);
        $this->assertSame('4.00', $corrected->hours_worked);
        $this->assertSame('320.00', $corrected->labor_cost);
    }

    public function testChangingTimesRecomputesLaborCost(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $corrected = $this->corrections->changeTimes($attendance, Carbon::parse('2026-10-21 07:00'), Carbon::parse('2026-10-21 11:20'), self::REASON, $this->admin)->refresh();

        $this->assertSame(260, $corrected->minutes_worked);
        $this->assertSame('4.33', $corrected->hours_worked);
        $this->assertSame('346.67', $corrected->labor_cost);
        $this->assertSame(Attendance::STATUS_ADJUSTED, $corrected->status);
        $this->assertSame('360.00', Attendance::withTrashed()->findOrFail($attendance->id)->labor_cost);
    }

    public function testChangingProjectMovesTheLaborCostBetweenProjectTotals(): void
    {
        $projectA = Project::factory()->ongoing()->create();
        $projectB = Project::factory()->ongoing()->create();
        $attendance = Attendance::factory()->for($projectA)->onDate('2026-10-21')->create();

        $this->assertSame('360.00', $projectA->laborTotal());
        $this->assertSame('0.00', $projectB->laborTotal());

        $corrected = $this->corrections->changeProject($attendance, $projectB, self::REASON, $this->admin);

        $this->assertSame('0.00', $projectA->laborTotal());
        $this->assertSame('360.00', $projectB->laborTotal());
        $this->assertSame(Attendance::STATUS_ADJUSTED, $corrected->refresh()->status);
        $this->assertSame($projectA->id, Attendance::withTrashed()->findOrFail($attendance->id)->project_id);
    }

    public function testSplittingCreatesTwoAttendancesWithTheSameProjectAndRate(): void
    {
        $project = Project::factory()->ongoing()->create();
        $attendance = Attendance::factory()->for($project)->onDate('2026-10-21')->create([
            'clock_out_at' => '2026-10-21 17:00:00',
            'clocked_minutes' => 600,
            'minutes_worked' => 600,
            'hours_worked' => '10.00',
            'labor_cost' => '800.00',
            'clock_in_photo_path' => 'attendance/in.jpg',
            'clock_out_photo_path' => 'attendance/out.jpg',
        ]);

        [$first, $second] = $this->corrections->split($attendance, Carbon::parse('2026-10-21 12:00'), self::REASON, $this->admin);
        $first->refresh();
        $second->refresh();

        $this->assertSame('2026-10-21 07:00', $first->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-21 12:00', $first->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame(300, $first->minutes_worked);
        $this->assertSame('400.00', $first->labor_cost);
        $this->assertSame('attendance/in.jpg', $first->clock_in_photo_path);
        $this->assertNull($first->clock_out_photo_path);

        $this->assertSame('2026-10-21 12:00', $second->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-21 17:00', $second->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame(300, $second->minutes_worked);
        $this->assertSame('400.00', $second->labor_cost);
        $this->assertNull($second->clock_in_photo_path);
        $this->assertSame('attendance/out.jpg', $second->clock_out_photo_path);

        foreach ([$first, $second] as $part) {
            $this->assertSame($project->id, $part->project_id);
            $this->assertSame($attendance->user_id, $part->user_id);
            $this->assertSame('80.00', $part->hourly_rate);
            $this->assertSame(Attendance::STATUS_ADJUSTED, $part->status);
            $this->assertSame($this->admin->id, $part->adjusted_by);
            $this->assertSame($attendance->id, $part->corrected_from_id);
        }

        $this->assertSoftDeleted($attendance);
        $this->assertSame('800.00', $project->laborTotal());
    }

    public function testVoidExcludesTheAttendanceFromTheLaborTotal(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->onDate('2026-10-21')->create();
        $afternoon = Attendance::factory()->for($project)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        $this->assertSame('720.00', $project->laborTotal());

        $voided = $this->corrections->void($afternoon, self::REASON, $this->admin)->refresh();

        $this->assertSame(Attendance::STATUS_VOID, $voided->status);
        $this->assertSame($this->admin->id, $voided->adjusted_by);
        $this->assertSame(self::REASON, $voided->adjustment_reason);
        $this->assertSame($afternoon->id, $voided->corrected_from_id);
        $this->assertSoftDeleted($afternoon);
        $this->assertSame('360.00', $project->laborTotal());
    }

    public function testACorrectedRecordCanBeCorrectedAgainAndTheHistoryKeepsEveryStep(): void
    {
        $original = Attendance::factory()->onDate('2026-10-21')->create(['clock_out_at' => '2026-10-21 17:00:00']);

        [, $afternoon] = $this->corrections->split($original, Carbon::parse('2026-10-21 12:00'), 'Missed the lunch clock-out.', $this->admin);
        $final = $this->corrections->changeTimes($afternoon, Carbon::parse('2026-10-21 13:00'), Carbon::parse('2026-10-21 17:00'), 'Came back from lunch at 1 PM.', $this->admin);

        $history = $this->corrections->history($final);

        $this->assertCount(2, $history);
        $this->assertTrue($history[0]['before']->is($original));
        $this->assertCount(2, $history[0]['after']);
        $this->assertSame('Missed the lunch clock-out.', $history[0]['reason']);
        $this->assertTrue($history[0]['by']->is($this->admin));
        $this->assertTrue($history[1]['before']->is($afternoon));
        $this->assertTrue($history[1]['after']->sole()->is($final));
        $this->assertSame('Came back from lunch at 1 PM.', $history[1]['reason']);

        $this->assertSame([], $this->corrections->history($original));
    }

    public function testAnArchivedOriginalCannotBeCorrectedAgain(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();
        $this->corrections->void($attendance, self::REASON, $this->admin);

        $this->assertValidationError('attendance', fn () => $this->corrections->void($attendance, self::REASON, $this->admin));
        $this->assertSame(2, Attendance::withTrashed()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidReasons(): array
    {
        return ['empty' => [''], 'blank' => ['          '], 'too short' => ['Wrong pro'], 'too long' => [str_repeat('a', 256)]];
    }

    #[DataProvider('invalidReasons')]
    public function testAReasonOfAtLeastTenCharactersIsRequired(string $reason): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $this->assertValidationError('reason', fn () => $this->corrections->void($attendance, $reason, $this->admin));
        $this->assertNotSoftDeleted($attendance);
        $this->assertSame(1, Attendance::withTrashed()->count());
    }

    public function testTheClockOutMustBeAfterTheClockIn(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $this->assertValidationError('clock_out', fn () => $this->corrections->changeTimes(
            $attendance, Carbon::parse('2026-10-21 11:00'), Carbon::parse('2026-10-21 11:00'), self::REASON, $this->admin,
        ));
    }

    public function testTimesCannotBeInTheFuture(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->open()->create();

        $this->assertValidationError('clock_out', fn () => $this->corrections->setClockOut(
            $attendance, Carbon::parse('2026-10-21 18:05'), self::REASON, $this->admin,
        ));
    }

    public function testTimesCannotOverlapTheUsersOtherAttendance(): void
    {
        $worker = User::factory()->worker()->create();
        $morning = Attendance::factory()->for($worker)->onDate('2026-10-21')->create();
        Attendance::factory()->for($worker)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        $this->assertValidationError('clock_out', fn () => $this->corrections->changeTimes(
            $morning, Carbon::parse('2026-10-21 07:00'), Carbon::parse('2026-10-21 12:45'), self::REASON, $this->admin,
        ));
        $this->assertNotSoftDeleted($morning);
        $this->assertSame(2, Attendance::withTrashed()->count());
    }

    public function testVoidArchivedAndOtherPeoplesAttendanceDoNotCountAsOverlap(): void
    {
        $worker = User::factory()->worker()->create();
        $morning = Attendance::factory()->for($worker)->onDate('2026-10-21')->create();
        Attendance::factory()->for($worker)->void()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:00:00',
            'clock_out_at' => '2026-10-21 13:00:00',
        ]);
        Attendance::factory()->for($worker)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:00:00',
            'clock_out_at' => '2026-10-21 13:00:00',
        ])->delete();
        Attendance::factory()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:00:00',
            'clock_out_at' => '2026-10-21 13:00:00',
        ]);

        $corrected = $this->corrections->changeTimes($morning, Carbon::parse('2026-10-21 07:00'), Carbon::parse('2026-10-21 13:00'), self::REASON, $this->admin);

        $this->assertSame(360, $corrected->refresh()->minutes_worked);
    }

    public function testASetClockOutCannotOverlapALaterAttendance(): void
    {
        $worker = User::factory()->worker()->create();
        $flagged = Attendance::factory()->for($worker)->onDate('2026-10-21')->flagged()->create();
        Attendance::factory()->for($worker)->open()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 13:00:00',
        ]);

        $this->assertValidationError('clock_out', fn () => $this->corrections->setClockOut(
            $flagged, Carbon::parse('2026-10-21 14:00'), self::REASON, $this->admin,
        ));
    }

    public function testAShiftAttendanceCannotBeMovedToAnotherDay(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-20')->create([
            'shift_windows' => [['start' => '2026-10-20 08:00', 'end' => '2026-10-20 12:00']],
        ]);

        $this->assertValidationError('clock_in', fn () => $this->corrections->changeTimes(
            $attendance, Carbon::parse('2026-10-21 08:00'), Carbon::parse('2026-10-21 12:00'), self::REASON, $this->admin,
        ));
        $this->assertNotSoftDeleted($attendance);
    }

    public function testAFlexibleAttendanceCanBeMovedToAnotherDay(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-20')->create();

        $corrected = $this->corrections->changeTimes($attendance, Carbon::parse('2026-10-21 07:00'), Carbon::parse('2026-10-21 11:30'), self::REASON, $this->admin)->refresh();

        $this->assertSame('2026-10-21', $corrected->work_date->toDateString());
        $this->assertSame('360.00', $corrected->labor_cost);
    }

    public function testTheSplitTimeMustBeInsideTheAttendance(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $this->assertValidationError('split_at', fn () => $this->corrections->split(
            $attendance, Carbon::parse('2026-10-21 11:30'), self::REASON, $this->admin,
        ));
        $this->assertSame(1, Attendance::withTrashed()->count());
    }

    public function testSplitAndChangeProjectNeedAClockOut(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create();

        $this->assertValidationError('split_at', fn () => $this->corrections->split(
            $attendance, Carbon::parse('2026-10-21 09:00'), self::REASON, $this->admin,
        ));
        $this->assertValidationError('project_id', fn () => $this->corrections->changeProject(
            $attendance, Project::factory()->ongoing()->create(), self::REASON, $this->admin,
        ));
    }

    public function testSetClockOutIsOnlyForAttendanceWithoutOne(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $this->assertValidationError('clock_out', fn () => $this->corrections->setClockOut(
            $attendance, Carbon::parse('2026-10-21 12:00'), self::REASON, $this->admin,
        ));
    }

    public function testVoidAttendanceCannotBeCorrected(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->void()->create();

        $this->assertValidationError('attendance', fn () => $this->corrections->changeTimes(
            $attendance, Carbon::parse('2026-10-21 07:00'), Carbon::parse('2026-10-21 12:00'), self::REASON, $this->admin,
        ));
    }

    public function testOnlyAUserWhoMayCorrectAttendanceCanMakeChanges(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $this->expectException(AuthorizationException::class);

        $this->corrections->void($attendance, self::REASON, User::factory()->staff()->create());
    }

    private function assertValidationError(string $key, callable $correction): void
    {
        try {
            $correction();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), 'Errors: '.json_encode($e->errors()));

            return;
        }

        $this->fail("Expected a validation error on [{$key}].");
    }
}
