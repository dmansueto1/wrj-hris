<?php

namespace Tests\Feature\SystemSettings;

use App\Livewire\Attendance\Clock;
use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Kiosk;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each System Settings value changes the rule that used to be fixed in code or .env (T-14.1).
 */
class SettingsEffectsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    public function testThePhotoSettingTurnsThePhotoStepOnAndOff(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();
        $this->actingAs($worker);

        $this->change(['attendance_require_photo' => true]);
        Livewire::test(Clock::class)->set('projectId', (string) $project->id)->call('clockIn')->assertHasErrors('photo');
        $this->assertSame(0, Attendance::count());

        $this->change(['attendance_require_photo' => false]);
        Livewire::test(Clock::class)->set('projectId', (string) $project->id)->call('clockIn')->assertHasNoErrors();
        $this->assertSame(1, Attendance::count());
    }

    public function testTheApprovedDeviceSettingShowsTheRequestAccessScreen(): void
    {
        $this->change(['attendance_require_approved_device' => true]);
        $this->get(route('kiosk'))->assertSee('This device needs approval');

        $this->change(['attendance_require_approved_device' => false]);
        $this->get(route('kiosk'))->assertDontSee('This device needs approval')->assertSeeHtml('data-test="kiosk-continue-button"');
    }

    public function testTheFlagTimeDecidesWhenOpenAttendanceIsFlagged(): void
    {
        $this->change(['attendance_flag_after_hours' => '8']);
        $early = $this->openAttendance('2026-10-19 07:00:00');
        $late = $this->openAttendance('2026-10-19 09:00:00');

        $this->travelTo('2026-10-19 16:00:00'); // 9 hours and 7 hours after clock-in
        $this->artisan('attendance:flag-open')->assertSuccessful();

        $this->assertSame(Attendance::STATUS_FLAGGED, $early->refresh()->status);
        $this->assertSame(Attendance::STATUS_OPEN, $late->refresh()->status);
    }

    public function testTheNeedsCorrectionTextNamesTheFlagTime(): void
    {
        $this->change(['attendance_flag_after_hours' => '8']);
        $this->actingAs($this->admin);

        Livewire::test(Index::class)
            ->set('needsCorrection', true)
            ->assertSee('more than 8 hours after clock-in');
    }

    public function testANewMarkupAppliesOnlyToNewEntries(): void
    {
        $old = ProjectMaterialEntry::factory()->create(['unit_price' => '30.00', 'quantity' => '100']);

        $this->change(['material_markup_rate' => '25']);
        $new = ProjectMaterialEntry::factory()->create(['unit_price' => '30.00', 'quantity' => '100']);

        $this->assertSame('0.2000', $old->refresh()->markup_rate);
        $this->assertSame('3600.00', $old->total_cost);
        $this->assertSame('0.2500', $new->refresh()->markup_rate);
        $this->assertSame('3750.00', $new->total_cost);
    }

    public function testTheKioskSignInTimeSendsTheWorkerBackAfterItRunsOut(): void
    {
        $this->change(['kiosk_verified_seconds' => '60']);
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $kiosk = $this->verify($worker)->set('projectId', (string) $project->id);
        $this->travel(61)->seconds();

        $kiosk->call('clockIn')
            ->assertHasErrors(['username' => 'Please enter your username and password again.'])
            ->assertSet('verifiedUserId', null);
        $this->assertSame(0, Attendance::count());
    }

    public function testTheKioskSignInTimeStillAllowsAQuickClockIn(): void
    {
        $this->change(['kiosk_verified_seconds' => '60']);
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $kiosk = $this->verify($worker)->set('projectId', (string) $project->id);
        $this->travel(59)->seconds();

        $kiosk->call('clockIn')->assertHasNoErrors();
        $this->assertSame(1, Attendance::count());
    }

    public function testTheIdleLogoutTimeAppliesToAdminAndStaffOnly(): void
    {
        $this->change(['idle_logout_minutes' => '10']);

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get(route('clock'))->assertOk();
            $this->travel(11)->minutes();
            $this->get(route('clock'))
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', 'You were logged out after 10 minutes of inactivity.');
            $this->assertGuest();
        }

        $this->actingAs(User::factory()->worker()->create())->get(route('clock'))->assertOk();
        $this->travel(11)->minutes();
        $this->get(route('clock'))->assertOk();
    }

    public function testTheLockoutSettingsChangeTheAttemptsAndTheMinutes(): void
    {
        $this->change(['login_max_attempts' => '3', 'login_lockout_minutes' => '10']);
        $user = User::factory()->worker()->create();

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong-password']);
        }
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('logout'));

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Too many failed login attempts. Please try again in 10 minutes.']);
        $this->assertGuest();

        // The kiosk shares the lock.
        Livewire::test(Kiosk::class)
            ->set('username', $user->username)
            ->set('password', 'password')
            ->call('verify')
            ->assertHasErrors('username')
            ->assertSet('verifiedUserId', null);

        $this->travel(11)->minutes();
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function testTheDeviceCleanUpDaysDecideWhichRequestsAreArchived(): void
    {
        $this->change(['device_prune_pending_days' => '7']);
        $old = Device::factory()->pending()->create(['last_seen_at' => now()->subDays(8)]);
        $recent = Device::factory()->pending()->create(['last_seen_at' => now()->subDays(6)]);

        $this->artisan('devices:prune-pending')->assertSuccessful();

        $this->assertSoftDeleted($old);
        $this->assertNotSoftDeleted($recent);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function change(array $values): void
    {
        app(SystemSettingsService::class)->update($values, $this->admin);
    }

    private function openAttendance(string $clockIn): Attendance
    {
        return Attendance::factory()->open()->create(['work_date' => substr($clockIn, 0, 10), 'clock_in_at' => $clockIn]);
    }

    private function verify(User $worker): Testable
    {
        return Livewire::test(Kiosk::class)
            ->set('username', $worker->username)
            ->set('password', 'password')
            ->call('verify');
    }
}
