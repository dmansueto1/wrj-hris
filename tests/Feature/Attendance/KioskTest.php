<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Kiosk;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    public function testAGuestCanOpenTheKiosk(): void
    {
        $this->get(route('kiosk'))
            ->assertOk()
            ->assertSee('Attendance Kiosk')
            ->assertSee('Username')
            ->assertSee('Password');

        $this->assertGuest();
    }

    public function testALoggedInUserCanAlsoOpenTheKiosk(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('kiosk'))
            ->assertOk();
    }

    public function testCorrectCredentialsOfAWorkerNotOnDutyShowClockInAndOngoingProjectsOnly(): void
    {
        User::factory()->worker()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->completed()->create(['code' => 'PRJ-002', 'name' => 'Finished School']);

        $this->verify('jdelacruz')
            ->assertHasNoErrors()
            ->assertSet('password', '')
            ->assertSee('Hi, Juan D.')
            ->assertSee('Riverside Townhomes')
            ->assertDontSee('Finished School')
            ->assertSeeHtml('data-test="kiosk-clock-in-button"')
            ->assertDontSeeHtml('data-test="kiosk-clock-out-button"');
    }

    public function testAWrongPasswordIsRejectedAndNothingIsCreated(): void
    {
        $worker = User::factory()->worker()->create();

        Livewire::test(Kiosk::class)
            ->set('username', $worker->username)
            ->set('password', 'wrong-password')
            ->call('verify')
            ->assertHasErrors(['username' => __('auth.failed')])
            ->assertSet('password', '')
            ->assertSet('verifiedUserId', null)
            ->assertDontSeeHtml('data-test="kiosk-clock-in-button"');

        $this->assertSame(0, Attendance::count());
    }

    public function testAnUnknownUsernameGetsTheSameMessage(): void
    {
        Livewire::test(Kiosk::class)
            ->set('username', 'nobody')
            ->set('password', 'password')
            ->call('verify')
            ->assertHasErrors(['username' => __('auth.failed')]);
    }

    public function testTheSixthAttemptAfterFiveFailuresIsLockedAndTheLoginPageToo(): void
    {
        $worker = User::factory()->worker()->create();
        $kiosk = Livewire::test(Kiosk::class);

        for ($i = 0; $i < 5; $i++) {
            $kiosk->set('username', $worker->username)->set('password', 'wrong-password')->call('verify');
        }

        $kiosk->set('username', $worker->username)
            ->set('password', 'password')
            ->call('verify')
            ->assertHasErrors(['username' => 'Too many failed login attempts. Please try again in 15 minutes.'])
            ->assertSet('verifiedUserId', null);

        $this->post(route('login.store'), ['username' => $worker->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Too many failed login attempts. Please try again in 15 minutes.']);
        $this->assertGuest();
    }

    public function testAnInactiveUserGetsTheInactiveMessage(): void
    {
        $worker = User::factory()->worker()->inactive()->create();

        $this->verify($worker->username)->assertHasErrors(['username' => __('auth.inactive')]);
    }

    public function testAUserWithoutTheClockPermissionIsRefused(): void
    {
        $user = User::factory()->create();

        $this->verify($user->username)
            ->assertHasErrors('username')
            ->assertSet('verifiedUserId', null);
    }

    public function testAUserWithATemporaryPasswordCanStillClockIn(): void
    {
        $worker = User::factory()->worker()->create(['must_change_password' => true]);
        $project = Project::factory()->ongoing()->create();

        $this->verify($worker->username)
            ->set('projectId', $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::open()->where('user_id', $worker->id)->count());
    }

    public function testClockingInCreatesAnOpenDutyAndResetsTheKiosk(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '85.50']);
        $project = Project::factory()->ongoing()->create();

        $this->verify($worker->username)
            ->set('projectId', $project->id)
            ->call('clockIn', 14.5995124, 120.9842195)
            ->assertHasNoErrors()
            ->assertSet('username', '')
            ->assertSet('password', '')
            ->assertSet('projectId', null)
            ->assertSet('verifiedUserId', null)
            ->assertSet('verifiedAt', null)
            ->assertDispatched('kiosk-duty-recorded');

        $duty = Attendance::sole();
        $this->assertSame(Attendance::STATUS_OPEN, $duty->status);
        $this->assertSame($project->id, $duty->project_id);
        $this->assertSame('85.50', $duty->hourly_rate);
        $this->assertSame('14.5995124', $duty->clock_in_lat);
        $this->assertFalse(auth()->check(), 'The kiosk never logs anyone in.');
    }

    public function testAWorkerOnDutySeesClockOutAndClockingOutClosesTheDuty(): void
    {
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);

        $this->travelTo('2026-03-02 07:00:00');
        $this->verify($worker->username)->set('projectId', $project->id)->call('clockIn');

        $this->travelTo('2026-03-02 11:30:00');
        $this->verify($worker->username)
            ->assertSee('Riverside Townhomes')
            ->assertSee('On Duty since 7:00 AM')
            ->assertSeeHtml('data-test="kiosk-clock-out-button"')
            ->assertDontSeeHtml('data-test="kiosk-clock-in-button"')
            ->call('clockOut')
            ->assertHasNoErrors()
            ->assertSet('verifiedUserId', null)
            ->assertSet('username', '');

        $duty = Attendance::sole();
        $this->assertSame(Attendance::STATUS_CLOSED, $duty->status);
        $this->assertSame(270, $duty->minutes_worked);
        $this->assertSame('4.50', $duty->hours_worked);
        $this->assertSame('360.00', $duty->labor_cost);
    }

    public function testClockingInWithoutVerifyingDoesNothing(): void
    {
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Kiosk::class)
            ->set('projectId', $project->id)
            ->call('clockIn')
            ->assertHasErrors('username')
            ->assertSet('verifiedUserId', null);

        $this->assertSame(0, Attendance::count());
    }

    public function testAVerificationOlderThanTwoMinutesMustBeRepeated(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $kiosk = $this->verify($worker->username)->set('projectId', $project->id);

        $this->travel(3)->minutes();

        $kiosk->call('clockIn')
            ->assertHasErrors(['username' => 'Please enter your username and password again.'])
            ->assertSet('verifiedUserId', null);

        $this->assertSame(0, Attendance::count());
    }

    public function testARoleRemovedAfterVerifyingBlocksTheClockIn(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $kiosk = $this->verify($worker->username)->set('projectId', $project->id);

        $worker->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $kiosk->call('clockIn')->assertHasErrors('username')->assertSet('verifiedUserId', null);

        $this->assertSame(0, Attendance::count());
    }

    public function testANonOngoingProjectIdShowsAnErrorAndCreatesNothing(): void
    {
        $worker = User::factory()->worker()->create();
        $completed = Project::factory()->completed()->create();

        $this->verify($worker->username)
            ->set('projectId', $completed->id)
            ->call('clockIn')
            ->assertHasErrors('kiosk')
            ->assertSee('You can only clock in to an Ongoing project.');

        $this->assertSame(0, Attendance::count());
    }

    public function testClockingInWithoutChoosingAProjectShowsAnError(): void
    {
        $worker = User::factory()->worker()->create();

        $this->verify($worker->username)->call('clockIn')->assertHasErrors('projectId');

        $this->assertSame(0, Attendance::count());
    }

    public function testStartOverClearsEverything(): void
    {
        $worker = User::factory()->worker()->create();

        $this->verify($worker->username)
            ->call('startOver')
            ->assertSet('verifiedUserId', null)
            ->assertSet('verifiedAt', null)
            ->assertSet('username', '')
            ->assertSet('projectId', null)
            ->assertSeeHtml('data-test="kiosk-continue-button"');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('roles')]
    public function testEveryRoleCanClockInAndOutAtTheKiosk(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $project = Project::factory()->ongoing()->create();

        $this->verify($user->username)->set('projectId', $project->id)->call('clockIn')->assertHasNoErrors();
        $this->verify($user->username)->call('clockOut')->assertHasNoErrors();

        $this->assertSame(Attendance::STATUS_CLOSED, Attendance::sole()->status);
    }

    private function verify(string $username, string $password = 'password'): Testable
    {
        return Livewire::test(Kiosk::class)
            ->set('username', $username)
            ->set('password', $password)
            ->call('verify');
    }

    public function testProjectsArePickedFromADropdownOfOngoingProjects(): void
    {
        $worker = User::factory()->worker()->create();
        $ongoing = Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->onHold()->create(['code' => 'PRJ-009', 'name' => 'Paused Warehouse']);

        $this->verify($worker->username)
            ->assertSeeHtml('data-test="kiosk-project-select"')
            ->assertSeeHtml('value="'.$ongoing->id.'"')
            ->assertSee('PRJ-001 · Riverside Townhomes')
            ->assertDontSee('Paused Warehouse');
    }
}
