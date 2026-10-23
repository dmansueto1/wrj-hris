<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AllAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday; the default range is Monday 2026-10-19 to Sunday 2026-10-25.
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

    public function testAdminSeesEveryColumn(): void
    {
        $worker = User::factory()->worker()->create(['employee_no' => 'EMP-0042', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);
        Attendance::factory()->for($worker)->for($project)->onDate('2026-10-20')->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertSee('Oct 20, 2026')
            ->assertDontSee('Employee no.')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Riverside Townhomes')
            ->assertSee('7:00 AM')
            ->assertSee('11:30 AM')
            ->assertSee('Worked time')
            ->assertSeeHtml('data-test="worked-hours">4.50 h')
            ->assertSee('₱ 80.00')
            ->assertSee('₱ 360.00')
            ->assertSee('Clocked Out');
    }

    public function testTheDateFilterExcludesAttendanceOutsideTheRange(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $thisWeek = Attendance::factory()->onDate('2026-10-20')->create();
        $lastWeek = Attendance::factory()->onDate('2026-10-12')->create();

        // Rows are checked by id: the filter dropdowns list every employee and project name.
        Livewire::test(Index::class)
            ->assertSet('from', '2026-10-19')
            ->assertSet('to', '2026-10-25')
            ->assertSeeHtml($this->row($thisWeek))
            ->assertDontSeeHtml($this->row($lastWeek))
            ->set('from', '2026-10-12')
            ->set('to', '2026-10-12')
            ->assertSeeHtml($this->row($lastWeek))
            ->assertDontSeeHtml($this->row($thisWeek));
    }

    public function testFiltersByUserProjectAndStatus(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $juan = User::factory()->worker()->create();
        $maria = User::factory()->worker()->create();
        $riverside = Project::factory()->ongoing()->create();
        $hillcrest = Project::factory()->ongoing()->create();
        $juanAtRiverside = Attendance::factory()->for($juan)->for($riverside)->onDate('2026-10-20')->create();
        $mariaAtHillcrest = Attendance::factory()->for($maria)->for($hillcrest)->onDate('2026-10-20')->open()->create();

        Livewire::test(Index::class)
            ->set('user', (string) $juan->id)
            ->assertSeeHtml($this->row($juanAtRiverside))
            ->assertDontSeeHtml($this->row($mariaAtHillcrest))
            ->set('user', '')
            ->set('project', (string) $hillcrest->id)
            ->assertSeeHtml($this->row($mariaAtHillcrest))
            ->assertDontSeeHtml($this->row($juanAtRiverside))
            ->set('project', '')
            ->set('status', 'open')
            ->assertSeeHtml($this->row($mariaAtHillcrest))
            ->assertDontSeeHtml($this->row($juanAtRiverside))
            ->call('clearFilters')
            ->assertSeeHtml($this->row($juanAtRiverside))
            ->assertSeeHtml($this->row($mariaAtHillcrest));
    }

    public function testTotalsCoverEveryFilteredAttendanceAcrossAllPagesAndSkipVoid(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // 12 counted (4.50 h, ₱ 360.00 each) spread over two pages, plus void and open ones.
        Attendance::factory()->count(12)->onDate('2026-10-20')->create();
        Attendance::factory()->onDate('2026-10-20')->void()->create();
        Attendance::factory()->onDate('2026-10-21')->open()->create();
        // Outside the range: not counted.
        Attendance::factory()->onDate('2026-10-12')->create();

        // The table's Total row shows the labor cost; the total hours are in the "Total hours" stat card.
        $component = Livewire::test(Index::class)
            ->assertSet('perPage', 10)
            ->assertSeeHtml('data-test="total-labor-cost">₱ 4,320.00')
            ->assertDontSeeHtml('data-test="total-hours"')
            ->assertDontSee('Only Clocked Out and Adjusted are counted', false)
            ->call('gotoPage', 2)
            ->assertSeeHtml('data-test="total-labor-cost">₱ 4,320.00');

        $this->assertSame(['hours' => '54.00', 'labor_cost' => '4320.00'], $component->instance()->totals);
    }

    public function testRowsArePaginatedWithRowsPerPage(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->count(12)->onDate('2026-10-20')->create();

        $component = Livewire::test(Index::class)->assertSeeHtml('data-test="per-page-select"');
        $this->assertSame(10, $component->instance()->attendances->count());
        $this->assertSame(12, $component->instance()->attendances->total());
    }

    public function testRecordsWithPhotosShowBothThumbnailsAndRecordsWithoutShowNoPhoto(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $withPhotos = Attendance::factory()->onDate('2026-10-20')->create([
            'clock_in_photo_path' => 'attendance-photos/2026/10/1-in-a.jpg',
            'clock_out_photo_path' => 'attendance-photos/2026/10/1-out-b.jpg',
        ]);
        Attendance::factory()->onDate('2026-10-21')->create();

        Livewire::test(Index::class)
            ->assertSeeHtml(e(route('attendance.photo', [$withPhotos, 'in'])))
            ->assertSeeHtml(e(route('attendance.photo', [$withPhotos, 'out'])))
            ->assertSeeHtml('loading="lazy"')
            ->assertSee('No photo');
    }

    public function testAClockedInRecordShowsOnlyTheClockInPhoto(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $open = Attendance::factory()->onDate('2026-10-21')->open()->create([
            'clock_in_photo_path' => 'attendance-photos/2026/10/1-in-a.jpg',
        ]);

        Livewire::test(Index::class)
            ->assertSeeHtml(e(route('attendance.photo', [$open, 'in'])))
            ->assertDontSeeHtml(e(route('attendance.photo', [$open, 'out'])));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function statuses(): array
    {
        return [
            'open' => [Attendance::STATUS_OPEN, 'Clocked In'],
            'closed' => [Attendance::STATUS_CLOSED, 'Clocked Out'],
            'adjusted' => [Attendance::STATUS_ADJUSTED, 'Adjusted'],
            'flagged' => [Attendance::STATUS_FLAGGED, 'Flagged'],
            'void' => [Attendance::STATUS_VOID, 'Void'],
        ];
    }

    #[DataProvider('statuses')]
    public function testEachStatusHasItsOnScreenLabel(string $status, string $label): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->onDate('2026-10-20')->create(['status' => $status]);

        Livewire::test(Index::class)
            ->set('status', $status)
            ->assertSeeHtml('data-test="status-'.$status.'"')
            ->assertSee($label);
    }

    public function testThePageNeverSaysSession(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->onDate('2026-10-20')->create();
        Attendance::factory()->onDate('2026-10-21')->open()->create();

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertSeeText('Clocked In')
            ->assertSeeText('Clocked Out')
            ->assertDontSeeText('session')
            ->assertDontSeeText('Session');
    }

    public function testRecordsOfArchivedUsersAndProjectsStillShow(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create(['first_name' => 'Pedro', 'last_name' => 'Penduko']);
        $project = Project::factory()->ongoing()->create(['name' => 'Old Bodega']);
        Attendance::factory()->for($worker)->for($project)->onDate('2026-10-20')->create();
        $worker->delete();
        $project->delete();

        Livewire::test(Index::class)
            ->assertSee('Pedro Penduko')
            ->assertSee('Old Bodega');
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function clockOuts(): array
    {
        return [
            'same day' => ['2026-10-20 17:00:00', null],
            'after midnight' => ['2026-10-21 06:00:00', '+1'],
            'two days later' => ['2026-10-22 09:00:00', '+2'],
        ];
    }

    #[DataProvider('clockOuts')]
    public function testTheOutTimeShowsHowManyDaysLaterItIs(string $clockOut, ?string $offset): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->create([
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 22:00:00',
            'clock_out_at' => $clockOut,
        ]);

        $component = Livewire::test(Index::class)->assertDontSee('next day');

        $offset === null
            ? $component->assertDontSeeHtml('data-test="clock-out-day-offset"')
            : $component->assertSeeHtml('data-test="clock-out-day-offset">'.$offset.'</sup>');
    }

    public function testAClockedInRecordShowsItsRunningWorkedTime(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        // Clocked in at 12:54:51 PM; now is 3:00:00 PM.
        Attendance::factory()->open()->create([
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 12:54:51',
        ]);

        Livewire::test(Index::class)
            ->assertSeeHtml('data-test="running-time"')
            ->assertSeeHtml('runningTime('.now()->setTime(12, 54, 51)->getTimestamp() * 1000 .')')
            ->assertSee('2h 05m 09s')
            ->assertDontSeeHtml('data-test="worked-hours"');
    }

    public function testClockedInIsGreenAndClockedOutIsGrayLikeTheClockScreens(): void
    {
        $this->assertSame('green', Attendance::factory()->open()->make()->statusColor());
        $this->assertSame('zinc', Attendance::factory()->make()->statusColor());
    }

    private function row(Attendance $attendance): string
    {
        return 'data-test="attendance-row-'.$attendance->id.'"';
    }
}
