<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Mine;
use App\Livewire\Dashboard;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceHistoryService;
use App\Services\AttendanceService;
use App\Services\SystemSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-16.1: a Late column after Worked time on every attendance table, measured against the shift
 * saved at clock-in, with Late totals on the Total rows. Display only: hours and labor cost do not change.
 */
class LateTest extends TestCase
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

    /**
     * Wednesday 2026-10-21 on 08:00–12:00 and 13:00–17:00: in 8:15 and 1:10 → 25 minutes late.
     */
    private function lateBothHalves(User $worker): void
    {
        $this->work($worker, '2026-10-21 08:15:00', '2026-10-21 12:00:00');
        $this->work($worker, '2026-10-21 13:10:00', '2026-10-21 17:00:00');
        $this->travelTo('2026-10-21 18:00:00');
    }

    public function testDailyAttendanceShowsLateAfterWorkedTime(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->lateBothHalves($worker);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['date' => '2026-10-21'])
            ->test(Index::class)
            ->assertSeeInOrder(['Worked time', 'Late', 'Photos'])
            ->assertSeeHtml('data-test="late-hours"')
            ->assertSee('0.42 h')
            ->assertSee('Late 25 min');
    }

    public function testLateIsTheSameAmberBadgeEverywhereAndAZeroTotalIsGray(): void
    {
        $late = User::factory()->worker()->twoShifts()->create();
        $onTime = User::factory()->worker()->twoShifts()->create();
        $this->lateBothHalves($late);
        $this->work($onTime, '2026-10-21 07:55:00', '2026-10-21 12:00:00');
        $this->travelTo('2026-10-21 18:00:00');

        // Day cell and phone card: amber badges.
        $this->actingAs(User::factory()->admin()->create());
        $html = Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->html();
        $this->assertMatchesRegularExpression('/<span[^>]*bg-warning-soft text-warning[^>]*data-test="late-hours"/', $html);
        $this->assertMatchesRegularExpression('/<span[^>]*bg-warning-soft text-warning[^>]*data-test="late-line"/', $html);

        // Total rows: amber badge when late, gray text at 0.00 h.
        $this->actingAs($late);
        $html = Livewire::withQueryParams(['from' => '2026-10-21', 'to' => '2026-10-21'])->test(Mine::class)->html();
        $this->assertMatchesRegularExpression('/<span[^>]*bg-warning-soft text-warning[^>]*data-test="range-total-late"/', $html);

        $this->actingAs($onTime);
        $html = Livewire::withQueryParams(['from' => '2026-10-21', 'to' => '2026-10-21'])->test(Mine::class)->html();
        $this->assertMatchesRegularExpression('/<span class="whitespace-nowrap tabular-nums text-zinc-400"\s+data-test="range-total-late"\s*>0\.00 h/', $html);
        $this->assertStringNotContainsString('bg-warning-soft', $html);
    }

    public function testByEmployeeShowsLatePerDayAndTotalsItAcrossPages(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->work($worker, '2026-10-02 08:30:00', '2026-10-02 12:00:00'); // Friday, 30 min
        $this->work($worker, '2026-10-20 08:15:00', '2026-10-20 12:00:00'); // Tuesday, 15 min
        $this->travelTo('2026-10-21 18:00:00');
        $this->actingAs(User::factory()->admin()->create());

        // Oct 1–21, newest first: Oct 20 is on page 1, Oct 2 on page 2.
        $page = Livewire::withQueryParams(['tab' => 'employee', 'employee' => (string) $worker->id, 'from' => '2026-10-01', 'to' => '2026-10-21'])
            ->test(Index::class)
            ->assertSeeInOrder(['Worked time', 'Late'])
            ->assertSee('0.25 h')
            ->assertDontSee('0.50 h')
            ->assertSeeHtml('data-test="employee-total-late"')
            ->assertSee('0.75 h');

        $this->assertSame(45, $page->instance()->employeeSummary['late_minutes']);
        $this->assertSame('0.75', $page->instance()->employeeSummary['late']);
    }

    public function testMyAttendanceShowsTheWorkersOwnLateAndTotal(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->lateBothHalves($worker);
        $this->actingAs($worker);

        Livewire::withQueryParams(['from' => '2026-10-19', 'to' => '2026-10-21'])
            ->test(Mine::class)
            ->assertSeeInOrder(['Worked time', 'Late'])
            ->assertSeeHtml('data-test="late-hours"')
            ->assertSeeHtml('data-test="range-total-late"')
            ->assertSee('0.42 h');
    }

    public function testTheDashboardTablesShowLate(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->lateBothHalves($worker);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(Dashboard::class)
            ->assertSeeInOrder(['Worked time', 'Late'])
            ->assertSee('0.42 h');

        $this->actingAs($worker);
        Livewire::test(Dashboard::class)
            ->assertSeeInOrder(['Worked time', 'Late'])
            ->assertSee('0.42 h')
            ->assertDontSeeHtml('data-test="range-total-late"');
    }

    public function testFlexibleAndRestDayAttendanceIsNeverLate(): void
    {
        $flexible = User::factory()->worker()->create(['last_name' => 'Flexible']);
        $this->work($flexible, '2026-10-21 09:00:00', '2026-10-21 12:00:00');

        // Sunday is the rest day of Two shifts a day.
        $shifted = User::factory()->worker()->twoShifts()->create();
        $this->work($shifted, '2026-10-18 08:30:00', '2026-10-18 12:00:00');
        $this->travelTo('2026-10-21 18:00:00');

        $this->assertSame(0, app(AttendanceHistoryService::class)->employeeSummary($flexible->id, '2026-10-21', '2026-10-21')['late_minutes']);
        $this->assertSame(0, app(AttendanceHistoryService::class)->employeeSummary($shifted->id, '2026-10-18', '2026-10-18')['late_minutes']);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->assertDontSeeHtml('data-test="late-hours"');
        Livewire::withQueryParams(['date' => '2026-10-18'])->test(Index::class)->assertDontSeeHtml('data-test="late-hours"');
    }

    public function testVoidAttendanceIsNotLate(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->twoShifts()->create();
        $attendance = $this->work($worker, '2026-10-21 08:15:00', '2026-10-21 12:00:00');
        $this->travelTo('2026-10-21 18:00:00');

        app(AttendanceCorrectionService::class)->void($attendance, 'Clocked in by mistake', $admin);

        $this->assertSame('0.00', app(AttendanceHistoryService::class)->employeeSummary($worker->id, '2026-10-21', '2026-10-21')['late']);
        $this->actingAs($admin);
        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->assertDontSeeHtml('data-test="late-hours"');
    }

    public function testACorrectionUpdatesLate(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->twoShifts()->create();
        $attendance = $this->work($worker, '2026-10-21 08:15:00', '2026-10-21 12:00:00');
        $this->travelTo('2026-10-21 18:00:00');
        $this->actingAs($admin);

        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->assertSee('0.25 h');

        app(AttendanceCorrectionService::class)->changeTimes(
            $attendance,
            Carbon::parse('2026-10-21 08:00:00'),
            Carbon::parse('2026-10-21 12:00:00'),
            'Was at the gate on time',
            $admin,
        );

        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)
            ->assertDontSee('0.25 h')
            ->assertDontSeeHtml('data-test="late-hours"');
    }

    public function testLateDoesNotChangeHoursOrLaborCost(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create(['hourly_rate' => '80.00']);
        $this->lateBothHalves($worker);

        // 225 + 230 = 455 counted minutes at ₱80.00/hr.
        $this->assertSame(455, (int) Attendance::query()->where('user_id', $worker->id)->sum('minutes_worked'));
        $this->assertSame('606.67', $this->project->laborTotal());
        $summary = app(AttendanceHistoryService::class)->employeeSummary($worker->id, '2026-10-21', '2026-10-21');
        $this->assertSame('7.58', $summary['hours']);
        $this->assertSame('606.67', $summary['labor_cost']);
        $this->assertSame('0.42', $summary['late']);
    }

    public function testTheGracePeriodForgivesShortLateness(): void
    {
        $admin = User::factory()->admin()->create();
        app(SystemSettingsService::class)->update(['attendance_late_grace_minutes' => '5'], $admin);

        $onTime = User::factory()->worker()->twoShifts()->create();
        $late = User::factory()->worker()->twoShifts()->create();
        $this->work($onTime, '2026-10-21 08:04:00', '2026-10-21 12:00:00');
        $this->work($late, '2026-10-21 08:07:00', '2026-10-21 12:00:00');
        $this->travelTo('2026-10-21 18:00:00');

        $history = app(AttendanceHistoryService::class);
        $this->assertSame(0, $history->employeeSummary($onTime->id, '2026-10-21', '2026-10-21')['late_minutes']);
        $this->assertSame('0.12', $history->employeeSummary($late->id, '2026-10-21', '2026-10-21')['late']);

        $this->actingAs($admin);
        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->assertSee('0.12 h');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGrace(): array
    {
        return ['below 0' => ['-1'], 'above 60' => ['61'], 'not a number' => ['abc']];
    }

    #[DataProvider('invalidGrace')]
    public function testTheGracePeriodIsValidated(string $value): void
    {
        $this->expectException(ValidationException::class);

        app(SystemSettingsService::class)->update(['attendance_late_grace_minutes' => $value], User::factory()->admin()->create());
    }

    public function testTheSystemSettingsPageListsTheGracePeriod(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('system-settings.index'))
            ->assertOk()
            ->assertSee('Late grace period');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdmins(): array
    {
        return ['staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('nonAdmins')]
    public function testOnlyTheAdminCanChangeTheGracePeriod(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('system-settings.index'))
            ->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(SystemSettingsService::class)->update(['attendance_late_grace_minutes' => '5'], $user);
    }

    public function testStaffSeeLateOnAllAttendanceAndWorkersOnlyTheirOwn(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $this->lateBothHalves($worker);

        $this->actingAs(User::factory()->staff()->create());
        Livewire::withQueryParams(['date' => '2026-10-21'])->test(Index::class)->assertSee('0.42 h');

        $this->actingAs($worker)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('attendance.mine'))->assertOk()->assertSee('Late');
    }
}
