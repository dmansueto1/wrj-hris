<?php

namespace Tests\Feature\Projects;

use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-16: the project page (/projects/{project}) with labor, materials, running expenses, budget
 * usage and the four breakdown tabs, plus the Running expenses column on /projects.
 */
class ProjectExpensesPageTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 15:00:00');

        // ₱360.00 labor (PRD 7.1) and ₱3,600.00 materials (PRD 7.2) on a ₱10,000.00 budget.
        $this->project = Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes', 'budget' => '10000.00', 'start_date' => '2026-10-01', 'end_date' => '2027-03-31']);
        Attendance::factory()->for($this->project)->onDate('2026-10-20')->create();
        ProjectMaterialEntry::factory()->for($this->project)->create(['entry_date' => '2026-10-20']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function roles(): array
    {
        return [
            'admin' => ['admin', 200],
            'staff' => ['staff', 200],
            'worker' => ['worker', 403],
        ];
    }

    #[DataProvider('roles')]
    public function testOnlyAdminAndStaffCanOpenTheProjectPage(string $role, int $status): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('projects.show', $this->project))
            ->assertStatus($status);
    }

    public function testWorkerGets403OnTheProjectPage(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->get(route('projects.show', $this->project))->assertForbidden();
        Livewire::test(Show::class, ['project' => $this->project])->assertForbidden();
    }

    public function testTheRouteChecksExpensesView(): void
    {
        $route = app('router')->getRoutes()->getByName('projects.show');

        $this->assertContains('permission:expenses.view', $route->gatherMiddleware());
    }

    public function testThePageShowsLaborMaterialsAndRunningExpenses(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Riverside Townhomes')
            ->assertSeeInOrder(['Running expenses', '₱ 3,960.00', 'Oct 1, 2026 – Oct 21, 2026', 'Labor', '₱ 360.00', '9.1% of expenses', 'Materials', '₱ 3,600.00', '90.9% of expenses'])
            ->assertDontSee('data-stat-card', false);
    }

    public function testRunningExpensesEqualLaborPlusMaterialsExactly(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->for($this->project)->onDate('2026-10-20')->create(['minutes_worked' => 260, 'hours_worked' => '4.33', 'labor_cost' => '346.67']);
        ProjectMaterialEntry::factory()->for($this->project)->create(['entry_date' => '2026-10-20', 'quantity' => '3', 'unit_price' => '0.33']); // ₱1.19

        // 360.00 + 346.67 = 706.67 labor; 3,600.00 + 1.19 = 3,601.19 materials; 4,307.86 in all.
        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('totals', ['labor' => '706.67', 'materials' => '3601.19', 'running' => '4307.86'])
            ->assertSeeHtml('data-test="total-labor"')
            ->assertSee('₱ 706.67')
            ->assertSee('₱ 3,601.19')
            ->assertSee('₱ 4,307.86');
    }

    public function testOpenFlaggedAndVoidAttendanceIsNotCountedAsLabor(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->for($this->project)->open()->create(['labor_cost' => '500.00']);
        Attendance::factory()->for($this->project)->flagged()->create(['labor_cost' => '500.00']);
        Attendance::factory()->for($this->project)->void()->create();

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('totals', ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'])
            ->assertSee('₱ 3,960.00');
    }

    public function testBudgetBarShows39Point6PercentUsed(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('budget.percent', '39.6')
            ->assertSee('39.6% used')
            ->assertSee('₱ 10,000.00')
            ->assertSeeHtml('aria-valuenow="39.6"')
            ->assertSeeHtml('data-level="normal"');
    }

    public function testBudgetBarTurnsAmberAt80AndRedAt100Percent(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->project->forceFill(['budget' => '4500.00'])->save(); // 3,960 of 4,500 = 88.0%
        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSee('88.0% used')
            ->assertSeeHtml('data-level="warning"')
            ->assertSeeHtml('bg-brand-gold');

        $this->project->forceFill(['budget' => '3960.00'])->save();
        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSee('100.0% used')
            ->assertSeeHtml('data-level="over"')
            ->assertSeeHtml('bg-danger');
    }

    public function testNoBudgetShowsNoBar(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->project->forceFill(['budget' => null])->save();

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('budget', null)
            ->assertSee('No budget set')
            ->assertDontSeeHtml('data-test="budget-bar"');
    }

    public function testLaborByWorkerShowsHoursAndCostPerWorker(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $ben = User::factory()->worker()->create(['first_name' => 'Ben', 'last_name' => 'Abad']);
        Attendance::factory()->for($this->project)->for($ana)->onDate('2026-10-19')->create();
        Attendance::factory()->for($this->project)->for($ana)->onDate('2026-10-20')->create();
        Attendance::factory()->for($this->project)->for($ben)->onDate('2026-10-20')->create(['minutes_worked' => 480, 'hours_worked' => '8.00', 'hourly_rate' => '95.50', 'labor_cost' => '764.00']);

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('tab', 'labor-worker')
            ->assertSeeInOrder(['Ben Abad', '8.00', '₱ 764.00'])
            ->assertSeeInOrder(['Ana Cruz', '9.00', '₱ 720.00']);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function tabs(): array
    {
        return [
            'Labor by date' => ['labor-date', ['Oct 20, 2026', '4.50', '₱ 360.00']],
            'Materials by item' => ['materials-item', ['Hollow blocks', '100.00', 'pc', '₱ 3,600.00']],
            'Materials by date' => ['materials-date', ['Oct 20, 2026', '1', '₱ 3,600.00']],
        ];
    }

    /**
     * @param  list<string>  $see
     */
    #[DataProvider('tabs')]
    public function testEachTabShowsItsBreakdown(string $tab, array $see): void
    {
        $this->actingAs(User::factory()->admin()->create());
        ProjectMaterialEntry::query()->update(['material_id' => Material::factory()->create(['name' => 'Hollow blocks', 'unit' => 'pc'])->id]);

        // Opened from the URL (?tab=…), so the assertions read the first render.
        Livewire::withQueryParams(['tab' => $tab])
            ->test(Show::class, ['project' => $this->project])
            ->assertSet('tab', $tab)
            ->assertSeeHtml('data-test="tab-'.$tab.'"')
            ->assertSeeInOrder($see);
    }

    public function testAnUnknownTabFallsBackToLaborByWorker(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['tab' => 'nope'])
            ->test(Show::class, ['project' => $this->project])
            ->assertSet('tab', 'labor-worker');
    }

    public function testTheDateFilterChangesTheTotals(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->for($this->project)->onDate('2026-10-15')->create();
        ProjectMaterialEntry::factory()->for($this->project)->create(['entry_date' => '2026-10-15']);

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('totals', ['labor' => '720.00', 'materials' => '7200.00', 'running' => '7920.00'])
            ->assertDontSeeHtml('data-test="active-filters"')
            ->set('from', '2026-10-18')
            ->assertSet('totals', ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'])
            ->assertSee('₱ 3,960.00')
            ->assertSeeHtml('data-test="active-filters"')
            ->set('to', '2026-10-19')
            ->assertSet('totals', ['labor' => '0.00', 'materials' => '0.00', 'running' => '0.00'])
            ->call('clearFilters')
            ->assertSet('from', '2026-10-01')
            ->assertSet('to', '2026-10-21')
            ->assertSet('totals', ['labor' => '720.00', 'materials' => '7200.00', 'running' => '7920.00']);
    }

    public function testTheRangeDefaultsToTheProjectStartUntilToday(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        // Before the start date: left out of the default range.
        Attendance::factory()->for($this->project)->onDate('2026-09-30')->create();

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('from', '2026-10-01')
            ->assertSet('to', '2026-10-21')
            ->assertSet('totals', ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'])
            ->assertDontSeeHtml('data-test="active-filters"');
    }

    public function testAProjectWithoutAStartDateHasNoDefaultRange(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->project->forceFill(['start_date' => null, 'end_date' => null])->save();
        Attendance::factory()->for($this->project)->onDate('2026-09-30')->create();

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSet('from', '')
            ->assertSet('to', '')
            ->assertSet('totals.labor', '720.00')
            ->assertSee('All dates')
            ->assertDontSeeHtml('data-test="active-filters"');
    }

    public function testDatesInTheUrlWinOverTheDefaults(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['from' => '2026-10-21'])
            ->test(Show::class, ['project' => $this->project])
            ->assertSet('from', '2026-10-21')
            ->assertSet('to', '')
            ->assertSet('totals.running', '0.00')
            ->assertSeeHtml('data-test="active-filters"');
    }

    public function testTheDateFilterAppliesToTheTabs(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Attendance::factory()->for($this->project)->for(User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']))->onDate('2026-10-15')->create();

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSee('Ana Cruz')
            ->set('from', '2026-10-18')
            ->assertDontSee('Ana Cruz')
            ->set('tab', 'labor-date')
            ->assertSee('Oct 20, 2026')
            ->assertDontSee('Oct 15, 2026');
    }

    public function testFromAfterToIsSwapped(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project])
            ->set('to', '2026-10-01')
            ->set('from', '2026-10-31')
            ->assertSet('from', '2026-10-01')
            ->assertSet('to', '2026-10-31')
            ->assertSet('totals.running', '3960.00');
    }

    public function testBudgetUsageFollowsTheDateFilter(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project])
            ->set('from', '2026-10-21')
            ->assertSet('budget.percent', '0.0');
    }

    public function testTheBreakdownTablesArePaginated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (range(1, 11) as $i) {
            Attendance::factory()->for($this->project)
                ->for(User::factory()->worker()->create(['first_name' => 'Worker', 'last_name' => sprintf('Zz%02d', $i)]))
                ->onDate('2026-10-20')->create();
        }

        Livewire::test(Show::class, ['project' => $this->project])
            ->assertSee('Rows per page')
            ->assertSee('Zz09')
            ->assertDontSee('Zz10')
            ->set('perPage', 25)
            ->assertSee('Zz11');
    }

    public function testEmptyTabsShowAnEmptyState(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Show::class, ['project' => $project])
            ->assertSee('No labor yet')
            ->set('tab', 'materials-item')
            ->assertSee('No material purchases yet');
    }

    public function testOnlyAdminSeesTheEditButton(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(Show::class, ['project' => $this->project])->assertSeeHtml('data-test="edit-project-button"');

        $this->actingAs(User::factory()->staff()->create());
        Livewire::test(Show::class, ['project' => $this->project])->assertDontSeeHtml('data-test="edit-project-button"');
    }

    public function testTheProjectsListShowsRunningExpenses(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $other = Project::factory()->ongoing()->create(['code' => 'PRJ-002']);
        Attendance::factory()->for($other)->open()->create(['labor_cost' => '500.00']);

        Livewire::test(Index::class)->assertSee('Running expenses')->assertSee('₱ 3,960.00');

        Livewire::withQueryParams(['view' => 'table'])
            ->test(Index::class)
            ->assertSeeHtml('data-flux-table')
            ->assertSee('Running expenses')
            ->assertSeeInOrder(['PRJ-001', '₱ 3,960.00', 'PRJ-002', '₱ 0.00']);
    }
}
