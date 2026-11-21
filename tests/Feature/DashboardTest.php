<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Project;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 2026-10-21, 3:00 PM.
        $this->travelTo('2026-10-21 15:00:00');
    }

    // ---- Access and landing --------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('roles')]
    public function testEveryRoleCanOpenTheDashboard(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function testAGuestIsSentToLogin(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function testLoggingInAndTheHomePageLandOnTheDashboard(): void
    {
        $worker = User::factory()->worker()->create();

        $this->post(route('login.store'), ['username' => $worker->username, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    // ---- Admin dashboard -----------------------------------------------------------------------

    public function testTheOverviewCountsToday(): void
    {
        $this->acceptanceData();

        $this->assertSame(
            ['clocked_in' => 2, 'present' => 3, 'employees' => 5, 'needs_correction' => 1, 'pending_devices' => 1],
            app(DashboardService::class)->overview(),
        );
    }

    public function testTheAdminDashboardShowsTheFourCardsAndTodaysAttendance(): void
    {
        $people = $this->acceptanceData();

        $component = Livewire::test(Dashboard::class)
            ->assertSee('Good afternoon, Admin!')
            ->assertSee('Wednesday, Oct 21, 2026 · Here is how today is going on site.')
            ->assertDontSee('Dashboard</h1>', false)
            ->assertSeeHtml('data-test="card-clocked-in"')
            ->assertSeeHtml('data-test="card-present"')
            ->assertSee('3 of 5')
            ->assertSeeHtml('data-test="card-needs-correction"')
            ->assertSeeHtml('data-test="card-pending-devices"')
            ->assertSeeHtml('href="'.route('attendance.index', ['needs_correction' => 1]).'"')
            ->assertSeeHtml('href="'.route('devices.index', ['status' => 'pending']).'"')
            ->assertSee("Today's Attendance")
            ->assertSee('Open All Attendance')
            ->assertDontSeeHtml('data-test="correct-');

        // Every employee by last name, the ones not clocked in yet included.
        $this->assertSame(
            ['Abad', 'Bautista', 'Cruz', 'Dizon', 'Zamora'],
            collect($component->instance()->todaysAttendance->items())->map(fn (array $row) => $row['user']->last_name)->all(),
        );
        foreach (['Abad', 'Bautista', 'Cruz', 'Dizon', 'Zamora'] as $name) {
            $component->assertSeeHtml('data-test="employee-row-'.$people[$name]->id.'"');
        }
        $component->assertSeeHtml('data-test="presence-absent"')->assertSeeHtml('data-test="presence-present"');
    }

    public function testTodaysAttendanceShowsRestDayAndIsPaginated(): void
    {
        // Eleven employees: Abad, Worker01–Worker09 and the admin, Zamora, last by name.
        $eleventh = User::factory()->admin()->create(['last_name' => 'Zamora']);
        $this->actingAs($eleventh);
        // Sunday 2026-10-25 is the factory shift's rest day.
        $this->travelTo('2026-10-25 09:00:00');
        $resting = User::factory()->worker()->oneShift()->create(['last_name' => 'Abad']);
        foreach (range(1, 9) as $i) {
            User::factory()->worker()->create(['last_name' => 'Worker'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $component = Livewire::test(Dashboard::class)
            ->assertSet('perPage', 10)
            ->assertSeeHtml('data-test="presence-rest-day"')
            ->assertSeeHtml('data-test="employee-row-'.$resting->id.'"')
            ->assertDontSeeHtml('data-test="employee-row-'.$eleventh->id.'"');

        $this->assertSame(11, $component->instance()->todaysAttendance->total());

        $component->call('gotoPage', 2)->assertSeeHtml('data-test="employee-row-'.$eleventh->id.'"');
    }

    // ---- My dashboard (Staff and Worker) -------------------------------------------------------

    #[DataProvider('personal')]
    public function testStaffAndWorkersSeeOnlyTheirOwnLastFourteenDays(string $role): void
    {
        $me = User::factory()->{$role}()->create(['first_name' => 'Ana', 'last_name' => 'Abad']);
        $other = User::factory()->worker()->create(['first_name' => 'Ben', 'last_name' => 'Bautista']);
        Attendance::factory()->for($me)->for(Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']))->onDate('2026-10-20')->create();
        Attendance::factory()->for($other)->for(Project::factory()->ongoing()->create(['name' => 'Hillcrest Warehouse']))->onDate('2026-10-20')->create();
        $this->actingAs($me);

        $component = Livewire::test(Dashboard::class)
            ->assertSee('Good afternoon, Ana!')
            ->assertSee('Wednesday, Oct 21, 2026 · Here is your attendance for the last 14 days.')
            ->assertSee('My Attendance')
            ->assertSee('See all in My Attendance')
            ->assertSee('Riverside Townhomes')
            ->assertDontSee('Hillcrest Warehouse')
            ->assertDontSee('Bautista')
            ->assertDontSeeHtml('data-test="card-clocked-in"')
            ->assertDontSee("Today's Attendance")
            ->assertDontSeeText('session');

        $dates = collect($component->instance()->myDays->items())->map(fn (array $day) => $day['date']->toDateString());
        $this->assertSame(14, $component->instance()->myDays->total());
        $this->assertSame(['2026-10-21', '2026-10-20', '2026-10-19'], $dates->take(3)->all());
        $this->assertTrue(collect($component->instance()->myDays->items())[1]['present']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function personal(): array
    {
        return ['staff' => ['staff'], 'worker' => ['worker']];
    }

    public function testTheClockCardShowsClockInOrClockOut(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        $this->actingAs($worker);

        Livewire::test(Dashboard::class)
            ->assertSee('Not clocked in')
            ->assertSeeHtml('data-test="dashboard-clock-in"')
            ->assertDontSeeHtml('data-test="dashboard-clock-out"')
            ->assertSeeHtml('href="'.route('clock').'"');

        Attendance::factory()->for($worker)->for($project)->open()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => Carbon::parse('2026-10-21 07:02:00'),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('You are Clocked In. Keep up the good work!')
            ->assertSee('Clocked In since 7:02 AM')
            ->assertSee('Riverside Townhomes')
            ->assertSeeHtml('data-test="dashboard-clock-out"')
            ->assertDontSeeHtml('data-test="dashboard-clock-in"');
    }

    public function testAdminCanOpenTheKioskInANewTabWithoutLoggingOut(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $html = Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-test="open-kiosk"')
            ->assertSee('Open kiosk')
            ->html();

        preg_match('/<a[^>]*data-test="open-kiosk"[^>]*>/', $html, $link);
        $this->assertNotEmpty($link, 'The Open kiosk button is not a link.');
        $this->assertStringContainsString('href="'.route('kiosk').'"', $link[0]);
        $this->assertStringContainsString('target="_blank"', $link[0]);
        $this->assertStringNotContainsString('wire:navigate', $link[0]);

        // Opening the kiosk keeps the Admin signed in.
        $this->get(route('kiosk'))->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('personal')]
    public function testStaffAndWorkersDoNotSeeTheKioskButton(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());

        Livewire::test(Dashboard::class)->assertDontSeeHtml('data-test="open-kiosk"');
    }

    public function testTheGreetingFollowsTheTimeOfDay(): void
    {
        $this->actingAs(User::factory()->worker()->create(['first_name' => 'Ana']));

        $this->travelTo('2026-10-21 07:00:00');
        Livewire::test(Dashboard::class)->assertSee('Good morning, Ana!')->assertSeeHtml('data-test="dashboard-greeting"');

        $this->travelTo('2026-10-21 19:30:00');
        Livewire::test(Dashboard::class)->assertSee('Good evening, Ana!');
    }

    public function testAFlaggedAttendanceShowsTheNotice(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->flagged()->create();
        $this->actingAs($worker);

        Livewire::test(Dashboard::class)
            ->assertSee('You have an attendance from Oct 19, 2026 that was not clocked out. Your admin will correct it.');
    }

    /**
     * Five active employees (the admin included), three with attendance today (two still Clocked
     * In), one flagged attendance from an earlier day, one pending device.
     *
     * @return array<string, User>
     */
    private function acceptanceData(): array
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Admin', 'last_name' => 'Zamora']);
        $this->actingAs($admin);

        $people = ['Zamora' => $admin];
        foreach (['Abad', 'Bautista', 'Cruz', 'Dizon'] as $last) {
            $people[$last] = User::factory()->worker()->create(['last_name' => $last]);
        }

        Attendance::factory()->for($people['Abad'])->onDate('2026-10-21')->create(); // Clocked Out
        foreach (['Bautista', 'Cruz'] as $last) {
            Attendance::factory()->for($people[$last])->open()->create(['work_date' => '2026-10-21', 'clock_in_at' => '2026-10-21 07:00:00']);
        }
        Attendance::factory()->for($people['Dizon'])->onDate('2026-10-19')->flagged()->create();
        Attendance::factory()->for($admin)->onDate('2026-10-21')->void()->create(); // never counted

        Device::factory()->pending()->create();
        Device::factory()->pending()->create()->delete(); // archived
        Device::factory()->approved()->create();

        return $people;
    }
}
