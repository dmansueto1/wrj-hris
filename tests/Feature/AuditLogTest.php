<?php

namespace Tests\Feature;

use App\Livewire\Attendance\Correction;
use App\Livewire\MaterialEntries\Edit as EditMaterialEntry;
use App\Livewire\Users\Edit as EditUser;
use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\MaterialEntryService;
use App\Services\UserRateService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * T-17: attendance corrections, rate, role and status changes and material entries record who did
 * it, when, what changed and why; Admin sees it in a History panel.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 15:00:00');
        $this->admin = User::factory()->admin()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    }

    // ---- Attendance corrections ----------------------------------------------------------------

    public function testChangeProjectCreatesOneActivityWithTheCauserOldAndNewProjectAndTheReason(): void
    {
        $projectA = Project::factory()->ongoing()->create(['code' => 'PRJ-A', 'name' => 'Riverside Townhomes']);
        $projectB = Project::factory()->ongoing()->create(['code' => 'PRJ-B', 'name' => 'Hilltop Warehouse']);
        $attendance = Attendance::factory()->for($projectA)->onDate('2026-10-20')->create();

        app(AttendanceCorrectionService::class)->changeProject($attendance, $projectB, 'Worker was at Hilltop all morning', $this->admin);

        $this->assertSame(1, Activity::query()->count());
        $activity = Activity::query()->first();
        $this->assertSame('attendance', $activity->log_name);
        $this->assertSame('Changed project', $activity->description);
        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertSame($attendance->id, $activity->subject_id);
        $this->assertSame($projectA->id, $activity->getProperty('old.project_id'));
        $this->assertSame($projectB->id, $activity->getProperty('new.project_id'));
        $this->assertSame('Worker was at Hilltop all morning', $activity->getProperty('reason'));
    }

    public function testTheCorrectedAttendanceHistoryShowsWhoWhenProjectAToBAndWhy(): void
    {
        $projectA = Project::factory()->ongoing()->create(['code' => 'PRJ-A', 'name' => 'Riverside Townhomes']);
        $projectB = Project::factory()->ongoing()->create(['code' => 'PRJ-B', 'name' => 'Hilltop Warehouse']);
        $attendance = Attendance::factory()->for($projectA)->onDate('2026-10-20')->create();
        $corrected = app(AttendanceCorrectionService::class)->changeProject($attendance, $projectB, 'Worker was at Hilltop all morning', $this->admin);

        $this->actingAs($this->admin);

        // The rendered HTML, not the JSON update response (which escapes "·").
        $html = Livewire::test(Correction::class)
            ->call('open', $corrected->id)
            ->assertSeeHtml('data-test="audit-history"')
            ->html();

        $this->assertSeeInOrderIn($html, ['History', 'Changed project', 'Maria Santos', 'Oct 21, 2026 3:00 PM', 'Project', 'PRJ-A · Riverside Townhomes', 'PRJ-B · Hilltop Warehouse', 'Worker was at Hilltop all morning']);
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function corrections(): array
    {
        return [
            'set clock-out' => ['setClockOut', 'Set clock-out', ['clock_out_at', 'minutes_worked', 'hours_worked', 'labor_cost', 'status']],
            'change times' => ['changeTimes', 'Changed times', ['clock_in_at', 'minutes_worked', 'hours_worked', 'labor_cost', 'status']],
            'void' => ['void', 'Voided attendance', ['status']],
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    #[DataProvider('corrections')]
    public function testEveryCorrectionIsLoggedWithItsOwnDescriptionAndOnlyTheChangedFields(string $action, string $description, array $fields): void
    {
        $service = app(AttendanceCorrectionService::class);
        $reason = 'Corrected after checking the site logbook';

        match ($action) {
            'setClockOut' => $service->setClockOut(Attendance::factory()->onDate('2026-10-20')->open()->create(), Carbon::parse('2026-10-20 11:30'), $reason, $this->admin),
            'changeTimes' => $service->changeTimes(Attendance::factory()->onDate('2026-10-20')->create(), Carbon::parse('2026-10-20 07:30'), Carbon::parse('2026-10-20 11:30'), $reason, $this->admin),
            'void' => $service->void(Attendance::factory()->onDate('2026-10-20')->create(), $reason, $this->admin),
        };

        $activity = Activity::query()->sole();
        $this->assertSame($description, $activity->description);
        $this->assertEqualsCanonicalizing($fields, array_keys($activity->getProperty('new')));
        $this->assertEqualsCanonicalizing($fields, array_keys($activity->getProperty('old')));
        $this->assertSame($reason, $activity->getProperty('reason'));
    }

    public function testChangeTimesLogsTheRecomputedLaborCost(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-20')->create(); // 7:00–11:30, ₱360.00

        app(AttendanceCorrectionService::class)->changeTimes($attendance, Carbon::parse('2026-10-20 07:00'), Carbon::parse('2026-10-20 11:20'), 'Left at 11:20, not 11:30', $this->admin);

        // MySQL's JSON column keeps its own key order, so compare the values, not the order.
        $activity = Activity::query()->sole();
        $this->assertEquals(['clock_out_at' => '2026-10-20 11:30', 'minutes_worked' => 270, 'hours_worked' => '4.50', 'labor_cost' => '360.00', 'status' => 'closed'], $activity->getProperty('old'));
        $this->assertEquals(['clock_out_at' => '2026-10-20 11:20', 'minutes_worked' => 260, 'hours_worked' => '4.33', 'labor_cost' => '346.67', 'status' => 'adjusted'], $activity->getProperty('new'));
    }

    public function testAFailedCorrectionLogsNothing(): void
    {
        $attendance = Attendance::factory()->onDate('2026-10-20')->create();

        try {
            app(AttendanceCorrectionService::class)->void($attendance, 'too short', $this->admin);
        } catch (ValidationException) {
        }

        $this->assertSame(0, Activity::query()->count());
    }

    public function testTheHistoryFollowsEveryCorrectionOfTheRecord(): void
    {
        $service = app(AttendanceCorrectionService::class);
        $first = $service->changeTimes(Attendance::factory()->onDate('2026-10-20')->create(), Carbon::parse('2026-10-20 07:00'), Carbon::parse('2026-10-20 11:20'), 'Left at 11:20, not 11:30', $this->admin);
        $second = $service->changeProject($first, Project::factory()->ongoing()->create(['name' => 'Hilltop Warehouse']), 'Worker was at Hilltop all morning', $this->admin);

        $this->actingAs($this->admin);

        Livewire::test(Correction::class)
            ->call('open', $second->id)
            ->assertSeeInOrder(['Changed project', 'Worker was at Hilltop all morning', 'Changed times', 'Left at 11:20, not 11:30']);
    }

    /**
     * @param  list<string>  $values
     */
    private function assertSeeInOrderIn(string $html, array $values): void
    {
        $position = 0;

        foreach ($values as $value) {
            $found = mb_strpos($html, e($value), $position);
            $this->assertNotFalse($found, "\"{$value}\" not found in order.");
            $position = $found + mb_strlen(e($value));
        }
    }

    // ---- Users -----------------------------------------------------------------------------------

    public function testARateChangeFrom80To90IsLoggedWithBothValues(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);

        app(UserRateService::class)->changeRate($worker, '90.00', $this->admin);

        $activity = Activity::query()->where('description', 'Changed hourly rate')->sole();
        $this->assertSame('users', $activity->log_name);
        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertTrue($activity->subject->is($worker));
        $this->assertSame(['hourly_rate' => '80.00'], $activity->getProperty('old'));
        $this->assertSame(['hourly_rate' => '90.00'], $activity->getProperty('new'));
    }

    public function testAnUnchangedRateLogsNothing(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);

        app(UserRateService::class)->changeRate($worker, '80', $this->admin);

        $this->assertSame(0, Activity::query()->count());
    }

    public function testRoleAndStatusChangesAreLogged(): void
    {
        $user = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $users = app(UserService::class);

        $users->update($user, [
            'employee_no' => $user->employee_no, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => null, 'role' => 'staff', 'position' => null, 'hourly_rate' => '80.00',
        ], $this->admin);
        $users->deactivate($user, $this->admin);
        $users->reactivate($user, $this->admin);

        $this->assertSame(['Changed role', 'Deactivated user', 'Reactivated user'], Activity::query()->orderBy('id')->pluck('description')->all());
        $role = Activity::query()->where('description', 'Changed role')->sole();
        $this->assertSame(['role' => 'worker'], $role->getProperty('old'));
        $this->assertSame(['role' => 'staff'], $role->getProperty('new'));
        $deactivated = Activity::query()->where('description', 'Deactivated user')->sole();
        $this->assertSame(['status' => 'active'], $deactivated->getProperty('old'));
        $this->assertSame(['status' => 'inactive'], $deactivated->getProperty('new'));
        $this->assertTrue(Activity::query()->get()->every(fn (Activity $activity) => $activity->causer?->is($this->admin)));
    }

    public function testTheUserPageHistoryShowsTheRateChange(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        app(UserRateService::class)->changeRate($worker, '90.00', $this->admin);

        $this->actingAs($this->admin);

        Livewire::test(EditUser::class, ['user' => $worker])
            ->assertSeeHtml('data-test="audit-history"')
            ->assertSeeInOrder(['History', 'Changed hourly rate', 'Maria Santos', 'Hourly rate', '₱ 80.00', '₱ 90.00']);
    }

    public function testResettingAPasswordWritesNoPasswordDataToTheActivityLog(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($this->admin);

        Livewire::test(EditUser::class, ['user' => $worker])
            ->set('new_password', 'Reset!Passw0rd9')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $activity = Activity::query()->sole();
        $this->assertSame('Reset password', $activity->description);
        $this->assertTrue($activity->causer->is($this->admin));

        $hash = (string) $worker->fresh()->getAuthPassword();
        foreach (DB::table('activity_log')->get() as $row) {
            $text = json_encode($row);
            $this->assertStringNotContainsString('Reset!Passw0rd9', $text);
            $this->assertStringNotContainsString($hash, $text);
            $this->assertStringNotContainsStringIgnoringCase('password"', (string) $row->properties.(string) $row->attribute_changes);
            $this->assertStringNotContainsString('remember_token', $text);
        }
    }

    // ---- Material entries ------------------------------------------------------------------------

    public function testUpdatingAMaterialEntryQuantityIsLogged(): void
    {
        $entry = ProjectMaterialEntry::factory()->create(['entry_date' => '2026-10-20']); // 100 × ₱30.00
        $this->actingAs($this->admin);

        app(MaterialEntryService::class)->update($entry, [
            'entry_date' => '2026-10-20', 'project_id' => $entry->project_id, 'material_id' => $entry->material_id,
            'quantity' => '150', 'unit_price' => '30.00', 'remarks' => null,
        ]);

        $activity = Activity::query()->where('description', 'Changed purchase')->sole();
        $this->assertSame('material-entries', $activity->log_name);
        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertSame('100.00', $activity->attribute_changes['old']['quantity']);
        $this->assertSame('150.00', $activity->attribute_changes['attributes']['quantity']);
        $this->assertSame('5400.00', $activity->attribute_changes['attributes']['total_cost']);
        $this->assertArrayNotHasKey('entry_date', $activity->attribute_changes['attributes']);
    }

    public function testRecordingAnEntryAsStaffIsAttributedToStaff(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);

        app(MaterialEntryService::class)->create($staff, [
            'entry_date' => '2026-10-21', 'project_id' => Project::factory()->ongoing()->create()->id,
            'material_id' => Material::factory()->create()->id, 'quantity' => '100', 'unit_price' => '30.00', 'remarks' => null,
        ]);

        $activity = Activity::query()->where('description', 'Recorded purchase')->sole();
        $this->assertTrue($activity->causer->is($staff));
        $this->assertSame('3600.00', $activity->attribute_changes['attributes']['total_cost']);
    }

    public function testArchivingAnEntryIsLogged(): void
    {
        $entry = ProjectMaterialEntry::factory()->create();
        $this->actingAs($this->admin);

        app(MaterialEntryService::class)->archive($entry);

        $activity = Activity::query()->where('description', 'Archived purchase')->sole();
        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertSame($entry->id, $activity->subject_id);
    }

    public function testTheMaterialEntryEditPageShowsItsHistoryToTheAdmin(): void
    {
        $entry = ProjectMaterialEntry::factory()->create(['entry_date' => '2026-10-21']);
        $this->actingAs($this->admin);
        app(MaterialEntryService::class)->update($entry, [
            'entry_date' => '2026-10-21', 'project_id' => $entry->project_id, 'material_id' => $entry->material_id,
            'quantity' => '150', 'unit_price' => '30.00', 'remarks' => null,
        ]);

        Livewire::test(EditMaterialEntry::class, ['materialEntry' => $entry->fresh()])
            ->assertSeeHtml('data-test="audit-history"')
            ->assertSeeInOrder(['History', 'Changed purchase', 'Maria Santos', 'Quantity', '100.00', '150.00', 'Total', '₱ 3,600.00', '₱ 5,400.00']);
    }

    // ---- Who sees the History ------------------------------------------------------------------

    public function testStaffCannotSeeTheHistoryPanel(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);
        $entry = app(MaterialEntryService::class)->create($staff, [
            'entry_date' => '2026-10-21', 'project_id' => Project::factory()->ongoing()->create()->id,
            'material_id' => Material::factory()->create()->id, 'quantity' => '100', 'unit_price' => '30.00', 'remarks' => null,
        ]);

        // Staff may edit their own entry made today, but without the History.
        Livewire::test(EditMaterialEntry::class, ['materialEntry' => $entry])
            ->assertOk()
            ->assertDontSeeHtml('data-test="audit-history"')
            ->assertDontSee('Recorded purchase');

        // The user page and the correction modal are Admin only.
        $this->get(route('users.edit', User::factory()->worker()->create()))->assertForbidden();
        Livewire::test(Correction::class)->assertForbidden();
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin', true], 'staff' => ['staff', false], 'worker' => ['worker', false]];
    }

    #[DataProvider('roles')]
    public function testOnlyTheAdminHasAuditView(string $role, bool $can): void
    {
        $this->assertSame($can, User::factory()->{$role}()->create()->can('audit.view'));
    }
}
