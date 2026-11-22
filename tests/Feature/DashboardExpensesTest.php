<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Projects\Index;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProjectExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Admin dashboard's Expenses row: running expenses this month across all projects, and the
 * projects at 80% or more of their budget (linked to the Projects list filtered the same way).
 */
class DashboardExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 2026-10-21, 3:00 PM: "this month" is Oct 1 to Oct 21.
        $this->travelTo('2026-10-21 15:00:00');
    }

    /**
     * PRJ-A ₱10,000 budget: ₱360 labor Oct 20 + ₱3,600 materials Oct 20 + ₱360 labor Sep 30 = 43.2%.
     * PRJ-B ₱4,000 budget: ₱3,600 materials Oct 5 = 90.0% (amber).
     * PRJ-C ₱3,000 budget: ₱3,600 materials Sep 15 = 120.0% (over).
     * PRJ-D no budget: ₱3,600 materials Oct 10.
     * PRJ-E archived, ₱1,000 budget: ₱3,600 materials Sep 1.
     */
    private function expenseData(): void
    {
        $a = Project::factory()->ongoing()->create(['code' => 'PRJ-A', 'budget' => '10000.00']);
        $b = Project::factory()->ongoing()->create(['code' => 'PRJ-B', 'budget' => '4000.00']);
        $c = Project::factory()->ongoing()->create(['code' => 'PRJ-C', 'budget' => '3000.00']);
        $d = Project::factory()->ongoing()->create(['code' => 'PRJ-D', 'budget' => null]);
        $e = Project::factory()->ongoing()->create(['code' => 'PRJ-E', 'budget' => '1000.00']);

        Attendance::factory()->for($a)->onDate('2026-10-20')->create();
        Attendance::factory()->for($a)->onDate('2026-09-30')->create();
        Attendance::factory()->for($a)->open()->create(['labor_cost' => '500.00']);
        Attendance::factory()->for($a)->void()->create();
        Attendance::factory()->for($a)->create()->delete();

        ProjectMaterialEntry::factory()->for($a)->create(['entry_date' => '2026-10-20']);
        ProjectMaterialEntry::factory()->for($a)->create(['entry_date' => '2026-10-20'])->delete();
        ProjectMaterialEntry::factory()->for($b)->create(['entry_date' => '2026-10-05']);
        ProjectMaterialEntry::factory()->for($c)->create(['entry_date' => '2026-09-15']);
        ProjectMaterialEntry::factory()->for($d)->create(['entry_date' => '2026-10-10']);
        ProjectMaterialEntry::factory()->for($e)->create(['entry_date' => '2026-09-01']);
        $e->delete();
    }

    public function testExpensesThisMonthAcrossAllProjects(): void
    {
        $this->expenseData();

        // Labor: ₱360 (Oct 20). Materials: ₱3,600 × 3 (Oct 5, 10, 20).
        $this->assertSame(
            ['labor' => '360.00', 'materials' => '10800.00', 'running' => '11160.00'],
            app(ProjectExpenseService::class)->overallTotals('2026-10-01', '2026-10-21'),
        );
        $this->assertSame(
            ['labor' => '360.00', 'materials' => '10800.00', 'running' => '11160.00'],
            app(DashboardService::class)->expenses()['month'],
        );
    }

    public function testBudgetWatchCountsProjectsAt80AndOver100Percent(): void
    {
        $this->expenseData();

        // PRJ-B (90%) and PRJ-C (120%); PRJ-A is at 43.2%, PRJ-D has no budget, PRJ-E is archived.
        $this->assertSame(['warning' => 2, 'over' => 1], app(ProjectExpenseService::class)->budgetWatch());
        $this->assertSame(['warning' => 2, 'over' => 1], app(DashboardService::class)->expenses()['budget']);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function budgetEdges(): array
    {
        return [
            'just under 80%' => ['7999.99', 0, 'none'],
            'exactly 80%' => ['8000.00', 1, 'warning'],
            'exactly 100%' => ['10000.00', 1, 'over'],
        ];
    }

    #[DataProvider('budgetEdges')]
    public function testBudgetWatchUsesExactAmounts(string $spent, int $warning, string $level): void
    {
        $project = Project::factory()->ongoing()->create(['budget' => '10000.00']);
        // A labor amount that hits the edge exactly.
        Attendance::factory()->for($project)->create(['labor_cost' => $spent]);

        $this->assertSame(
            ['warning' => $warning, 'over' => $level === 'over' ? 1 : 0],
            app(ProjectExpenseService::class)->budgetWatch(),
        );
    }

    public function testTheAdminDashboardShowsTheExpenseCards(): void
    {
        $this->expenseData();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Expenses', 'Expenses this month', '₱ 11,160.00', 'Labor ₱ 360.00 · Materials ₱ 10,800.00'])
            ->assertSeeInOrder(['Projects near budget', '2', '1 over budget'])
            ->assertSee('data-test="card-expenses-month"', false)
            ->assertSee('data-test="card-budget-watch"', false)
            ->assertSee('href="'.route('projects.index', ['budget' => 'watch']).'"', false);
    }

    public function testNoProjectNearBudgetIsCalm(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('₱ 0.00')
            ->assertSee('At 80% or more of budget');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function personalRoles(): array
    {
        return ['staff' => ['staff'], 'worker' => ['worker']];
    }

    #[DataProvider('personalRoles')]
    public function testStaffAndWorkersDoNotSeeTheExpenseCards(string $role): void
    {
        $this->expenseData();
        $this->actingAs(User::factory()->{$role}()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Expenses this month')
            ->assertDontSee('data-test="card-budget-watch"', false);

        Livewire::test(Dashboard::class)->assertDontSee('₱ 11,160.00');
    }

    public function testTheProjectsListFiltersByBudgetUsed(): void
    {
        $this->expenseData();
        $this->actingAs(User::factory()->staff()->create());

        Livewire::withQueryParams(['budget' => 'watch'])
            ->test(Index::class)
            ->assertSee('PRJ-B')
            ->assertSee('PRJ-C')
            ->assertDontSee('PRJ-A')
            ->assertDontSee('PRJ-D')
            ->assertSee('Budget: 80% or more used')
            ->set('budget', 'over')
            ->assertSee('PRJ-C')
            ->assertDontSee('PRJ-B')
            ->call('clearFilters')
            ->assertSet('budget', '')
            ->assertSee('PRJ-A');
    }

    public function testAnUnknownBudgetFilterShowsEveryProject(): void
    {
        $this->expenseData();
        $this->actingAs(User::factory()->staff()->create());

        Livewire::withQueryParams(['budget' => 'nope'])
            ->test(Index::class)
            ->assertSee('PRJ-A')
            ->assertSee('PRJ-D');
    }
}
