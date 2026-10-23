<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Mine;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday; the default range is Monday 2026-10-19 to Sunday 2026-10-25.
        $this->travelTo('2026-10-21 15:00:00');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('roles')]
    public function testEveryRoleCanOpenTheirOwnAttendance(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('attendance.mine'))
            ->assertOk()
            ->assertSee('My Attendance');
    }

    public function testAGuestIsSentToTheLoginPage(): void
    {
        $this->get(route('attendance.mine'))->assertRedirect(route('login'));
    }

    public function testWorkerADoesNotSeeWorkerBsAttendance(): void
    {
        $workerA = User::factory()->worker()->create();
        $workerB = User::factory()->worker()->create();
        Attendance::factory()->for($workerA)->for(Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']))->onDate('2026-10-19')->create();
        Attendance::factory()->for($workerB)->for(Project::factory()->ongoing()->create(['name' => 'Hillcrest Warehouse']))->onDate('2026-10-19')->create();

        $this->actingAs($workerA);

        Livewire::test(Mine::class)
            ->assertSee('Riverside Townhomes')
            ->assertDontSee('Hillcrest Warehouse');
    }

    public function testTheDefaultRangeIsTheCurrentWeekFromMondayToSunday(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Mine::class)
            ->assertSet('from', '2026-10-19')
            ->assertSet('to', '2026-10-25');
    }

    public function testTheDateFilterExcludesAttendanceOutsideTheRange(): void
    {
        $worker = User::factory()->worker()->create();
        $inside = Project::factory()->ongoing()->create(['name' => 'Inside Project']);
        $before = Project::factory()->ongoing()->create(['name' => 'Last Week Project']);
        $after = Project::factory()->ongoing()->create(['name' => 'Next Month Project']);
        Attendance::factory()->for($worker)->for($inside)->onDate('2026-10-20')->create();
        Attendance::factory()->for($worker)->for($before)->onDate('2026-10-18')->create();
        Attendance::factory()->for($worker)->for($after)->onDate('2026-11-02')->create();

        $this->actingAs($worker);

        Livewire::test(Mine::class)
            ->assertSee('Inside Project')
            ->assertDontSee('Last Week Project')
            ->assertDontSee('Next Month Project')
            ->set('from', '2026-10-01')
            ->set('to', '2026-10-31')
            ->assertSee('Last Week Project')
            ->assertDontSee('Next Month Project');
    }

    public function testAttendanceIsGroupedByDayWithTheRangeTotal(): void
    {
        $worker = User::factory()->worker()->create();
        $projectA = Project::factory()->ongoing()->create(['name' => 'Project A']);
        $projectB = Project::factory()->ongoing()->create(['name' => 'Project B']);

        // Monday: morning on A and afternoon on B, 4.50 + 4.50 hours (PRD 7.1).
        Attendance::factory()->for($worker)->for($projectA)->onDate('2026-10-19')->create();
        Attendance::factory()->for($worker)->for($projectB)->create([
            'work_date' => '2026-10-19',
            'clock_in_at' => '2026-10-19 12:30:00',
            'clock_out_at' => '2026-10-19 17:00:00',
        ]);
        // Tuesday: one morning, plus a void one that does not count.
        Attendance::factory()->for($worker)->for($projectA)->onDate('2026-10-20')->create();
        Attendance::factory()->for($worker)->for($projectA)->onDate('2026-10-20')->void()->create();

        $this->actingAs($worker);

        Livewire::test(Mine::class)
            ->assertSeeHtml('data-test="day-2026-10-19"')
            ->assertSeeHtml('data-test="day-2026-10-20"')
            ->assertDontSee('Day total')
            ->assertSeeHtml('data-test="range-total">13.50</span> h')
            ->assertSee('Monday, Oct 19')
            ->assertSee('7:00 AM')
            ->assertSee('11:30 AM')
            ->assertSee('12:30 PM')
            ->assertSee('5:00 PM')
            ->assertSee('Void');
    }

    public function testTheOwnPageShowsNoPesoAmountsAndNoPhotos(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->create([
            'clock_in_photo_path' => 'attendance-photos/2026/10/1-in-a.jpg',
            'clock_out_photo_path' => 'attendance-photos/2026/10/1-out-b.jpg',
        ]);

        $html = $this->actingAs($worker)->get(route('attendance.mine'))->assertOk()->getContent();

        $this->assertStringNotContainsString('₱', $html);
        $this->assertStringNotContainsString('360.00', $html);
        $this->assertStringNotContainsString('/photo/in', $html);
        $this->assertStringNotContainsString('/photo/out', $html);
    }

    public function testOpenAndClosedAttendanceShowClockedInAndClockedOutAndNeverSession(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-20')->create();
        Attendance::factory()->for($worker)->onDate('2026-10-21')->open()->create();

        $this->actingAs($worker)
            ->get(route('attendance.mine'))
            ->assertOk()
            ->assertSeeText('Clocked In')
            ->assertSeeText('Clocked Out')
            ->assertDontSeeText('session')
            ->assertDontSeeText('Session');
    }

    public function testAnEmptyRangeShowsTheEmptyState(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Mine::class)
            ->assertSee('No attendance in this range.')
            ->assertDontSeeHtml('data-test="range-total"');
    }

    public function testAReversedOrInvalidRangeIsCorrected(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(Mine::class)
            ->set('from', '2026-10-25')
            ->set('to', '2026-10-19')
            ->assertSet('from', '2026-10-19')
            ->assertSet('to', '2026-10-25')
            ->set('from', 'not-a-date')
            ->assertSet('from', '2026-10-19');
    }

    public function testDaysArePaginatedWithRowsPerPage(): void
    {
        $worker = User::factory()->worker()->create();
        foreach (range(1, 12) as $day) {
            Attendance::factory()->for($worker)->onDate(sprintf('2026-10-%02d', $day))->create();
        }

        $this->actingAs($worker);

        // Newest day first; ten days per page by default.
        Livewire::test(Mine::class)
            ->set('from', '2026-10-01')
            ->set('to', '2026-10-31')
            ->assertSet('perPage', 10)
            ->assertSeeHtml('data-test="per-page-select"')
            ->assertSeeHtml('data-test="day-2026-10-12"')
            ->assertSeeHtml('data-test="day-2026-10-03"')
            ->assertDontSeeHtml('data-test="day-2026-10-02"')
            ->assertSeeHtml('data-test="range-total">54.00')
            ->call('gotoPage', 2)
            ->assertSeeHtml('data-test="day-2026-10-01"');
    }

    public function testAttendanceIsShownInATableWithPhoneCards(): void
    {
        $worker = User::factory()->worker()->create();
        Attendance::factory()->for($worker)->onDate('2026-10-19')->create();

        $this->actingAs($worker);

        Livewire::test(Mine::class)
            ->assertSeeHtml('data-flux-table')
            ->assertSeeInOrder(['Date', 'Project', 'In', 'Out', 'Worked time', 'Status'])
            ->assertSeeHtml('data-test="worked-hours">4.50 h')
            ->assertDontSee('Day total')
            ->assertDontSee('Oct 19 to Oct 25')
            ->assertSeeHtml('md:hidden');
    }
}
