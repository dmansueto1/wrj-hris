<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Correction;
use App\Livewire\Attendance\Index;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Forgot to clock out at lunch.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 18:00:00');
    }

    public function testAdminSetsTheClockOutOfAFlaggedAttendance(): void
    {
        $admin = $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create(['hourly_rate' => '80.00']);

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->assertSet('action', Correction::SET_CLOCK_OUT)
            ->set('clock_out', '2026-10-21T11:30')
            ->set('reason', self::REASON)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('attendance-corrected');

        $corrected = Attendance::query()->sole();
        $this->assertSame('4.50', $corrected->hours_worked);
        $this->assertSame('360.00', $corrected->labor_cost);
        $this->assertSame(Attendance::STATUS_ADJUSTED, $corrected->status);
        $this->assertSame($admin->id, $corrected->adjusted_by);
        $this->assertSame($attendance->id, $corrected->corrected_from_id);
        $this->assertSoftDeleted($attendance);
    }

    public function testChangingTheProjectMovesTheLaborCost(): void
    {
        $this->signInAdmin();
        $projectA = Project::factory()->ongoing()->create();
        $projectB = Project::factory()->ongoing()->create();
        $attendance = Attendance::factory()->for($projectA)->onDate('2026-10-21')->create();

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::CHANGE_PROJECT)
            ->set('project_id', (string) $projectB->id)
            ->set('reason', 'Worked at Project B that morning.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0.00', $projectA->laborTotal());
        $this->assertSame('360.00', $projectB->laborTotal());
    }

    public function testSplitAtNoon(): void
    {
        $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->create(['clock_out_at' => '2026-10-21 17:00:00']);

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::SPLIT)
            ->set('split_at', '2026-10-21T12:00')
            ->set('reason', 'Missed the lunch clock-out.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([300, 300], Attendance::query()->orderBy('clock_in_at')->pluck('minutes_worked')->all());
    }

    public function testVoidExcludesTheAttendance(): void
    {
        $this->signInAdmin();
        $project = Project::factory()->ongoing()->create();
        $attendance = Attendance::factory()->for($project)->onDate('2026-10-21')->create();

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::VOID)
            ->set('reason', 'Clocked in by mistake.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Attendance::STATUS_VOID, Attendance::query()->sole()->status);
        $this->assertSoftDeleted($attendance);
        $this->assertSame('0.00', $project->laborTotal());
    }

    public function testAMissingReasonFailsValidation(): void
    {
        $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->flagged()->create();

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('clock_out', '2026-10-21T11:30')
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['reason'])
            ->assertNotDispatched('attendance-corrected');

        $this->assertNotSoftDeleted($attendance);
        $this->assertSame(1, Attendance::withTrashed()->count());
    }

    public function testOverlappingTimesFailValidation(): void
    {
        $this->signInAdmin();
        $worker = User::factory()->worker()->create();
        $morning = Attendance::factory()->for($worker)->onDate('2026-10-21')->create();
        Attendance::factory()->for($worker)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        Livewire::test(Correction::class)
            ->call('open', $morning->id)
            ->assertSet('action', Correction::CHANGE_TIMES)
            ->assertSet('clock_in', '2026-10-21T07:00')
            ->assertSet('clock_out', '2026-10-21T11:30')
            ->set('clock_out', '2026-10-21T13:00')
            ->set('reason', self::REASON)
            ->call('save')
            ->assertHasErrors(['clock_out']);

        $this->assertNotSoftDeleted($morning);
        $this->assertSame(2, Attendance::withTrashed()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notAdmins(): array
    {
        return ['staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('notAdmins')]
    public function testStaffAndWorkersCannotOpenOrCallTheCorrectionActions(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());
        session(['auth.password_confirmed_at' => time()]);
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Correction::class)->assertForbidden();

        $this->assertNotSoftDeleted($attendance);
    }

    public function testStaffCallingTheActionGets403(): void
    {
        $admin = $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $component = Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::VOID)
            ->set('reason', 'Clocked in by mistake.');

        // The same component, now called by a Staff member.
        $admin->syncRoles('staff');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin->fresh());

        $component->call('save')->assertForbidden();

        $this->assertNotSoftDeleted($attendance);
        $this->assertSame(1, Attendance::withTrashed()->count());
    }

    public function testAStalePasswordConfirmationSendsTheAdminToConfirmIt(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->assertRedirect(route('password.confirm'));

        session(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1]);

        Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->assertRedirect(route('password.confirm'));
    }

    public function testAdminSeesTheCorrectButtonOnEachRow(): void
    {
        $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Index::class)
            ->assertSeeHtml('data-test="correct-'.$attendance->user_id.'"')
            ->assertSeeHtml('data-test="correct-attendance-'.$attendance->id.'"')
            ->assertSeeLivewire(Correction::class);
    }

    public function testStaffDoNotSeeTheCorrectButton(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Index::class)
            ->assertDontSeeHtml('data-test="correct-attendance-'.$attendance->id.'"')
            ->assertDontSeeLivewire(Correction::class);
    }

    public function testSavingARecordThatWasAlreadyCorrectedShowsAMessage(): void
    {
        $admin = $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        $component = Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::VOID)
            ->set('reason', 'Clocked in by mistake.');

        // Another Admin corrects it first.
        app(AttendanceCorrectionService::class)->void($attendance, 'Duplicate attendance record.', $admin);

        $component->call('save')
            ->assertNoRedirect()
            ->assertDispatched('attendance-corrected');

        $this->assertSame(2, Attendance::withTrashed()->count());
    }

    public function testAnArchivedProjectFailsValidation(): void
    {
        $this->signInAdmin();
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();
        $archived = Project::factory()->ongoing()->create();

        $component = Livewire::test(Correction::class)
            ->call('open', $attendance->id)
            ->set('action', Correction::CHANGE_PROJECT)
            ->set('project_id', (string) $archived->id)
            ->set('reason', 'Worked at the other site.');

        $archived->delete();

        $component->call('save')->assertHasErrors(['project_id']);

        $this->assertNotSoftDeleted($attendance);
    }

    public function testAdminComparesTheCorrectedRecordWithTheOriginal(): void
    {
        $admin = $this->signInAdmin();
        $projectA = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        $projectB = Project::factory()->ongoing()->create(['name' => 'Hilltop Mall Annex']);
        $original = Attendance::factory()->for($projectA)->onDate('2026-10-21')->create();
        $corrected = app(AttendanceCorrectionService::class)->changeProject($original, $projectB, 'Worked at the mall that morning.', $admin);

        Livewire::test(Correction::class)
            ->call('compare', $corrected->id)
            ->assertSet('compareId', $corrected->id)
            ->assertSeeHtml('data-test="original-'.$original->id.'"')
            ->assertSeeHtml('data-test="corrected-'.$corrected->id.'"')
            ->assertSee('Riverside Townhomes')
            ->assertSee('Hilltop Mall Annex')
            ->assertSee('Worked at the mall that morning.')
            ->assertSee($admin->name)
            ->assertSee('₱ 360.00');
    }

    public function testTheRowMenuOffersTheComparisonOnlyForCorrectedRecords(): void
    {
        $admin = $this->signInAdmin();
        $worker = User::factory()->worker()->create();
        $untouched = Attendance::factory()->for($worker)->onDate('2026-10-21')->create();
        $original = Attendance::factory()->for($worker)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);
        $corrected = app(AttendanceCorrectionService::class)->changeTimes($original, Carbon::parse('2026-10-21 13:00'), Carbon::parse('2026-10-21 17:00'), self::REASON, $admin);

        Livewire::test(Index::class)
            ->assertSeeHtml('data-test="compare-attendance-'.$corrected->id.'"')
            ->assertDontSeeHtml('data-test="compare-attendance-'.$untouched->id.'"')
            ->assertDontSeeHtml('data-test="correct-attendance-'.$original->id.'"');
    }

    public function testStaffCannotCompare(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $attendance = Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Index::class)->assertDontSeeHtml('data-test="compare-attendance-'.$attendance->id.'"');
        Livewire::test(Correction::class)->assertForbidden();
    }

    private function signInAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        session(['auth.password_confirmed_at' => time()]);

        return $admin;
    }
}
