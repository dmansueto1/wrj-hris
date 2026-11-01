<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AllAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday; today is 2026-10-21 and this week is Monday 2026-10-19 to Sunday 2026-10-25.
        $this->travelTo('2026-10-21 15:00:00');
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'staff' => ['staff', 200], 'worker' => ['worker', 403]];
    }

    #[DataProvider('roles')]
    public function testOnlyAdminAndStaffCanOpenAllAttendance(string $role, int $status): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('attendance.index'))
            ->assertStatus($status);
    }

    public function testAGuestIsSentToTheLoginPage(): void
    {
        $this->get(route('attendance.index'))->assertRedirect(route('login'));
    }

    // ---- Daily attendance tab -------------------------------------------------------------

    public function testTheDailyTabListsEveryEmployeeByLastNameForToday(): void
    {
        $this->signInAdmin(); // Zamora: also an employee, last in the list
        $cruz = $this->worker('Carl', 'Cruz');
        $abad = $this->worker('Ana', 'Abad');
        $bautista = $this->worker('Ben', 'Bautista');

        $component = Livewire::test(Index::class)
            ->assertSet('tab', 'daily')
            ->assertSet('date', '2026-10-21')
            ->assertSet('user', '')
            ->assertSeeHtml('data-test="tab-daily"')
            ->assertSeeHtml('data-test="tab-employee"')
            ->assertSee('All employees');

        $this->assertRowsInOrder($component, [$abad, $bautista, $cruz]);
        $this->assertSame(
            ['Abad', 'Bautista', 'Cruz', 'Zamora'],
            collect($component->instance()->daily->items())->map(fn (array $row) => $row['user']->last_name)->all(),
        );
    }

    public function testPresentAndAbsentForTheSelectedDate(): void
    {
        $this->signInAdmin();
        $present = $this->worker('Ana', 'Abad');
        $absent = $this->worker('Ben', 'Bautista');
        $voidOnly = $this->worker('Carl', 'Cruz');
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        Attendance::factory()->for($present)->for($project)->onDate('2026-10-21')->create();
        Attendance::factory()->for($voidOnly)->onDate('2026-10-21')->void()->create();

        $component = Livewire::test(Index::class)
            ->assertSee('Riverside Townhomes')
            ->assertSee('7:00 AM')
            ->assertSee('11:30 AM')
            ->assertSeeHtml('data-test="worked-hours">4.50 h')
            ->assertSee('₱ 80.00')
            ->assertSee('₱ 360.00')
            ->assertSeeHtml('data-test="presence-present"')
            ->assertSeeHtml('data-test="presence-absent"');

        $presence = collect($component->instance()->daily->items())
            ->mapWithKeys(fn (array $row) => [$row['user']->last_name => $row['present']])
            ->all();
        $this->assertSame(['Abad' => true, 'Bautista' => false, 'Cruz' => false, 'Zamora' => false], $presence);

        // Four employees counting the admin: 1 present, 3 absent, ₱ 360.00 of labor.
        $this->assertSame(
            ['employees' => 4, 'present' => 1, 'absent' => 3, 'labor_cost' => '360.00'],
            $component->instance()->dailySummary,
        );
        $component->assertSeeHtml('data-test="total-labor-cost">₱ 360.00');
    }

    public function testMorningAndAfternoonShowTwoRowsButCountAsOnePresentEmployee(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');
        Attendance::factory()->for($worker)->onDate('2026-10-21')->create();
        Attendance::factory()->for($worker)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        $component = Livewire::test(Index::class)->set('user', (string) $worker->id);

        $row = $component->instance()->daily->items()[0];
        $this->assertCount(2, $row['attendances']);
        $this->assertTrue($row['present']);
        $this->assertSame(1, $component->instance()->dailySummary['present']);
        $component->assertSee('12:30 PM')->assertSee('5:00 PM');
    }

    public function testChangingTheDateAndTheEmployee(): void
    {
        $this->signInAdmin();
        $abad = $this->worker('Ana', 'Abad');
        $this->worker('Ben', 'Bautista');
        Attendance::factory()->for($abad)->onDate('2026-10-20')->create();

        $component = Livewire::test(Index::class);
        $this->assertFalse($this->presenceOf($component, $abad));

        $component->set('date', '2026-10-20');
        $this->assertTrue($this->presenceOf($component, $abad));

        $component->set('date', 'not-a-date')->assertSet('date', '2026-10-21');

        $component->set('user', (string) $abad->id);
        $this->assertSame(1, $component->instance()->daily->total());
        $this->assertSame($abad->id, $component->instance()->daily->items()[0]['user']->id);
    }

    public function testInactiveAndArchivedEmployeesShowOnlyOnDaysTheyHaveAttendance(): void
    {
        $this->signInAdmin();
        $inactive = $this->worker('Ina', 'Inactive', ['status' => User::STATUS_INACTIVE]);
        $archived = $this->worker('Arnel', 'Archived');
        $archived->delete();

        $component = Livewire::test(Index::class);
        $this->assertNotContains($inactive->id, $this->listedIds($component));
        $this->assertNotContains($archived->id, $this->listedIds($component));

        Attendance::factory()->for($inactive)->onDate('2026-10-21')->create();
        Attendance::factory()->for($archived)->onDate('2026-10-21')->create();

        $component = Livewire::test(Index::class);
        $this->assertContains($inactive->id, $this->listedIds($component));
        $this->assertContains($archived->id, $this->listedIds($component));
    }

    public function testTheDailyTabIsPaginatedByEmployee(): void
    {
        $this->signInAdmin();
        foreach (range(1, 12) as $i) {
            $this->worker('Worker', sprintf('Name%02d', $i));
        }

        // 12 workers and the admin: 10 on page 1, 3 on page 2.
        $component = Livewire::test(Index::class)
            ->assertSet('perPage', 10)
            ->assertSeeHtml('data-test="per-page-select"');
        $this->assertCount(10, $component->instance()->daily->items());
        $this->assertSame(13, $component->instance()->daily->total());

        $component->call('gotoPage', 2);
        $this->assertCount(3, $component->instance()->daily->items());
    }

    public function testTheProjectFilterShowsOnlyWhoWorkedOnThatProject(): void
    {
        $this->signInAdmin();
        $riverside = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        $hillcrest = Project::factory()->ongoing()->create(['name' => 'Hillcrest Warehouse']);
        $abad = $this->worker('Ana', 'Abad');
        $bautista = $this->worker('Ben', 'Bautista');
        $this->worker('Carl', 'Cruz'); // absent
        Attendance::factory()->for($abad)->for($riverside)->onDate('2026-10-21')->create();
        // Bautista: morning on Hillcrest, afternoon on Riverside.
        Attendance::factory()->for($bautista)->for($hillcrest)->onDate('2026-10-21')->create();
        Attendance::factory()->for($bautista)->for($riverside)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        $component = Livewire::test(Index::class)
            ->assertSee('All projects')
            ->set('project', (string) $riverside->id);

        // Only the two who worked on Riverside, each with only their Riverside record.
        $this->assertSame([$abad->id, $bautista->id], $this->listedIds($component));
        $this->assertSame(
            [[$riverside->id], [$riverside->id]],
            collect($component->instance()->daily->items())->map(fn (array $row) => $row['attendances']->pluck('project_id')->all())->all(),
        );
        // Two people present on Riverside, ₱ 720.00 of Riverside labor (2 × 4.50 h at ₱ 80.00).
        $this->assertSame(
            ['employees' => 2, 'present' => 2, 'absent' => 0, 'labor_cost' => '720.00'],
            $component->instance()->dailySummary,
        );
        $component->assertSeeHtml('data-test="active-filters"')
            ->assertSee('Project: Riverside Townhomes')
            ->call('clearFilters')
            ->assertSet('project', '');
    }

    public function testTheProjectChipNamesAProjectThatIsNoLongerOngoing(): void
    {
        $this->signInAdmin();
        $completed = Project::factory()->completed()->create(['code' => 'PRJ-007', 'name' => 'Old Warehouse']);
        Attendance::factory()->for($this->worker('Ana', 'Abad'))->for($completed)->onDate('2026-10-21')->create();

        $component = Livewire::test(Index::class)
            ->set('project', (string) $completed->id)
            ->assertSee('Project: Old Warehouse');
        $this->assertSame(1, $component->instance()->daily->total());
    }

    public function testTheProjectDropdownShowsCodeAndName(): void
    {
        $this->signInAdmin();
        Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);

        Livewire::test(Index::class)->assertSee('PRJ-001 · Riverside Townhomes');
    }

    public function testSortByProjectAndByWorkedTime(): void
    {
        $this->signInAdmin(); // Zamora, absent
        $zulu = Project::factory()->ongoing()->create(['name' => 'Zulu Tower']);
        $alpha = Project::factory()->ongoing()->create(['name' => 'Alpha Homes']);
        $abad = $this->worker('Ana', 'Abad');           // Zulu, 4.50 h
        $bautista = $this->worker('Ben', 'Bautista');   // Alpha, 4.50 + 4.50 h
        $cruz = $this->worker('Carl', 'Cruz');          // absent
        Attendance::factory()->for($abad)->for($zulu)->onDate('2026-10-21')->create();
        Attendance::factory()->for($bautista)->for($alpha)->onDate('2026-10-21')->create();
        Attendance::factory()->for($bautista)->for($alpha)->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:30:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        $component = Livewire::test(Index::class)->assertSet('sortBy', 'name');
        $this->assertSame(['Abad', 'Bautista', 'Cruz', 'Zamora'], $this->listedLastNames($component));

        // Project A–Z; absent people last, by last name.
        $component->set('sortBy', 'project');
        $this->assertSame(['Bautista', 'Abad', 'Cruz', 'Zamora'], $this->listedLastNames($component));

        // Most worked time first; absent people last, by last name.
        $component->set('sortBy', 'worked_time');
        $this->assertSame(['Bautista', 'Abad', 'Cruz', 'Zamora'], $this->listedLastNames($component));

        // An unknown sort falls back to Name.
        $component->set('sortBy', 'salary')->assertSet('sortBy', 'name');
        $this->assertSame(['Abad', 'Bautista', 'Cruz', 'Zamora'], $this->listedLastNames($component));
    }

    public function testSortingKeepsEachEmployeeOnOneRowAndTheCountsRight(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');
        foreach (['07:00', '13:00'] as $time) {
            Attendance::factory()->for($worker)->create([
                'work_date' => '2026-10-21',
                'clock_in_at' => "2026-10-21 {$time}:00",
                'clock_out_at' => Carbon::parse("2026-10-21 {$time}:00")->addMinutes(270),
            ]);
        }

        foreach (['project', 'worked_time'] as $sort) {
            $component = Livewire::test(Index::class)->set('sortBy', $sort);
            $this->assertSame(2, $component->instance()->daily->total(), $sort); // Abad and the admin, once each
            $this->assertSame(2, $component->instance()->dailySummary['employees'], $sort);
        }
    }

    // ---- By employee tab --------------------------------------------------------------------

    public function testTheByEmployeeTabWaitsForAnEmployee(): void
    {
        $this->signInAdmin();
        $this->worker('Ana', 'Abad');

        Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->assertSet('employee', '')
            ->assertSeeHtml('data-test="choose-employee"')
            ->assertSee('Select an employee to see their attendance.')
            ->assertDontSeeHtml('data-flux-table');
    }

    public function testTheByEmployeeTabListsEveryDayOfTheRangeWithPresentAndAbsent(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');
        Attendance::factory()->for($worker)->onDate('2026-10-19')->create();
        Attendance::factory()->for($worker)->onDate('2026-10-21')->create();

        $component = Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $worker->id)
            ->assertSet('from', '2026-10-19')
            ->assertSet('to', '2026-10-25')
            ->assertDontSeeHtml('data-test="choose-employee"');

        $days = collect($component->instance()->employeeDays->items())
            ->mapWithKeys(fn (array $day) => [$day['date']->toDateString() => $day['present']])
            ->all();
        $this->assertSame([
            '2026-10-25' => false,
            '2026-10-24' => false,
            '2026-10-23' => false,
            '2026-10-22' => false,
            '2026-10-21' => true,
            '2026-10-20' => false,
            '2026-10-19' => true,
        ], $days);

        $this->assertSame(
            // Today is the 21st: only the 20th is absent; the 22nd to the 25th have not happened yet.
            ['present_days' => 2, 'absent_days' => 1, 'hours' => '9.00', 'labor_cost' => '720.00'],
            $component->instance()->employeeSummary,
        );
        $component->assertSeeHtml('data-test="employee-total-hours">9.00 h')
            ->assertSeeHtml('data-test="employee-total-labor-cost">₱ 720.00');
    }

    public function testFutureDaysShowADashInsteadOfAbsent(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');
        Attendance::factory()->for($worker)->onDate('2026-10-19')->create();

        // Daily attendance for tomorrow: nobody is Absent yet.
        $daily = Livewire::test(Index::class)
            ->set('date', '2026-10-22')
            ->assertSeeHtml('data-test="presence-none"')
            ->assertDontSeeHtml('data-test="presence-absent"');
        $this->assertTrue($daily->instance()->daily->items()[0]['future']);
        $this->assertSame(['employees' => 2, 'present' => 0, 'absent' => 0, 'labor_cost' => '0.00'], $daily->instance()->dailySummary);

        // By employee for this week: Monday present, Tuesday and today absent, the rest still to come.
        $byEmployee = Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $worker->id);
        $states = collect($byEmployee->instance()->employeeDays->items())
            ->mapWithKeys(fn (array $day) => [$day['date']->toDateString() => $day['future'] ? 'future' : ($day['present'] ? 'present' : 'absent')])
            ->all();
        $this->assertSame([
            '2026-10-25' => 'future',
            '2026-10-24' => 'future',
            '2026-10-23' => 'future',
            '2026-10-22' => 'future',
            '2026-10-21' => 'absent',
            '2026-10-20' => 'absent',
            '2026-10-19' => 'present',
        ], $states);
        $this->assertSame(2, $byEmployee->instance()->employeeSummary['absent_days']);
        $byEmployee->assertSeeHtml('data-test="presence-none"');
    }

    public function testARangeLongerThan92DaysIsShortened(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');

        $component = Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $worker->id)
            ->set('from', '2026-01-01')
            ->set('to', '2026-10-21')
            ->assertSee('Showing the last 92 days of the range');

        $this->assertSame(92, $component->instance()->employeeDays->total());
        $this->assertSame('2026-10-21', $component->instance()->employeeDays->items()[0]['date']->toDateString());
    }

    public function testTheTabAndFiltersComeFromTheUrl(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');

        Livewire::withQueryParams(['tab' => 'employee', 'employee' => (string) $worker->id])
            ->test(Index::class)
            ->assertSet('tab', 'employee')
            ->assertSet('employee', (string) $worker->id)
            ->assertDontSeeHtml('data-test="choose-employee"');

        Livewire::withQueryParams(['tab' => 'something-else'])
            ->test(Index::class)
            ->assertSet('tab', 'daily');
    }

    public function testStatusesArePresentOrAbsentAndThePageNeverSaysSession(): void
    {
        $this->signInAdmin();
        Attendance::factory()->for($this->worker('Ana', 'Abad'))->onDate('2026-10-21')->create();
        Attendance::factory()->for($this->worker('Ben', 'Bautista'))->onDate('2026-10-21')->open()->create();

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertSeeHtml('data-test="presence-present"')
            ->assertSeeHtml('data-test="presence-absent"')
            ->assertDontSeeText('Clocked Out')
            ->assertDontSeeText('session')
            ->assertDontSeeText('Session');
    }

    public function testAPagePastTheEndShowsNoDaysOutsideTheRange(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Ana', 'Abad');

        // This week is 7 days, so with 10 rows per page there is no page 2.
        $component = Livewire::withQueryParams(['tab' => 'employee', 'employee' => (string) $worker->id, 'page' => 2])
            ->test(Index::class);

        $this->assertSame([], $component->instance()->employeeDays->items());
        $this->assertSame(7, $component->instance()->employeeDays->total());
    }

    public function testPhotoLabelsUseTheEmployeeWithoutExtraQueries(): void
    {
        $this->signInAdmin();
        foreach (['Abad', 'Bautista', 'Cruz'] as $i => $last) {
            Attendance::factory()->for($this->worker('Worker', $last))->onDate('2026-10-21')->create([
                'clock_in_photo_path' => "attendance-photos/2026/10/{$i}-in.jpg",
            ]);
        }
        $archived = $this->worker('Pedro', 'Penduko');
        Attendance::factory()->for($archived)->onDate('2026-10-21')->create([
            'clock_in_photo_path' => 'attendance-photos/2026/10/9-in.jpg',
        ]);
        $archived->delete();

        DB::enableQueryLog();
        $daily = Livewire::test(Index::class);
        $lazyUserLoads = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'from `users` where `users`.`id` = ?'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $lazyUserLoads);
        $daily->assertSee('Clock In photo of Pedro Penduko', false);

        // The By employee tab names the archived employee too.
        Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $archived->id)
            ->assertSee('Clock In photo of Pedro Penduko', false);
    }

    // ---- Kept from T-11: photos, running time, +1 marker, archived records ---------------------

    public function testRecordsWithPhotosShowBothThumbnailsAndRecordsWithoutShowNoPhoto(): void
    {
        $this->signInAdmin();
        $withPhotos = Attendance::factory()->for($this->worker('Ana', 'Abad'))->onDate('2026-10-21')->create([
            'clock_in_photo_path' => 'attendance-photos/2026/10/1-in-a.jpg',
            'clock_out_photo_path' => 'attendance-photos/2026/10/1-out-b.jpg',
        ]);
        Attendance::factory()->for($this->worker('Ben', 'Bautista'))->onDate('2026-10-21')->create();

        Livewire::test(Index::class)
            ->assertSeeHtml(e(route('attendance.photo', [$withPhotos, 'in'])))
            ->assertSeeHtml(e(route('attendance.photo', [$withPhotos, 'out'])))
            ->assertSeeHtml('loading="lazy"')
            ->assertSee('No photo');
    }

    public function testAClockedInRecordShowsOnlyItsClockInPhotoAndItsRunningTime(): void
    {
        $this->signInAdmin();
        $open = Attendance::factory()->for($this->worker('Ana', 'Abad'))->open()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:54:51',
            'clock_in_photo_path' => 'attendance-photos/2026/10/1-in-a.jpg',
        ]);

        Livewire::test(Index::class)
            ->assertSeeHtml(e(route('attendance.photo', [$open, 'in'])))
            ->assertDontSeeHtml(e(route('attendance.photo', [$open, 'out'])))
            ->assertSeeHtml('data-test="running-time"')
            ->assertSee('2h 05m 09s')
            ->assertSeeHtml('data-test="presence-present"');
    }

    public function testTheOutTimeShowsHowManyDaysLaterItIs(): void
    {
        $this->signInAdmin();
        Attendance::factory()->for($this->worker('Ana', 'Abad'))->create([
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 22:00:00',
            'clock_out_at' => '2026-10-21 06:00:00',
        ]);

        Livewire::test(Index::class)
            ->set('date', '2026-10-20')
            ->assertSeeHtml('data-test="clock-out-day-offset">+1</sup>');
    }

    public function testRecordsOfArchivedUsersAndProjectsStillShow(): void
    {
        $this->signInAdmin();
        $worker = $this->worker('Pedro', 'Penduko');
        $project = Project::factory()->ongoing()->create(['name' => 'Old Bodega']);
        Attendance::factory()->for($worker)->for($project)->onDate('2026-10-21')->create();
        $worker->delete();
        $project->delete();

        Livewire::test(Index::class)
            ->assertSee('Penduko, Pedro')
            ->assertSee('Old Bodega');
    }

    public function testClockedInIsGreenAndClockedOutIsGrayLikeTheClockScreens(): void
    {
        $this->assertSame('green', Attendance::factory()->open()->make()->statusColor());
        $this->assertSame('zinc', Attendance::factory()->make()->statusColor());
    }

    // ---- Helpers --------------------------------------------------------------------------

    private function signInAdmin(): User
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Admin', 'last_name' => 'Zamora']);
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function worker(string $first, string $last, array $attributes = []): User
    {
        return User::factory()->worker()->create(['first_name' => $first, 'last_name' => $last, ...$attributes]);
    }

    private function presenceOf(Testable $component, User $user): bool
    {
        return (bool) collect($component->instance()->daily->items())
            ->first(fn (array $row) => $row['user']->id === $user->id)['present'];
    }

    /**
     * @return list<string>
     */
    private function listedLastNames(Testable $component): array
    {
        return collect($component->instance()->daily->items())->map(fn (array $row) => $row['user']->last_name)->values()->all();
    }

    /**
     * @return list<int>
     */
    private function listedIds(Testable $component): array
    {
        return collect($component->instance()->daily->items())->map(fn (array $row) => $row['user']->id)->values()->all();
    }

    /**
     * @param  list<User>  $users
     */
    private function assertRowsInOrder(Testable $component, array $users): void
    {
        $html = $component->html();
        $positions = array_map(fn (User $user) => strpos($html, 'data-test="employee-row-'.$user->id.'"'), $users);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }
}
