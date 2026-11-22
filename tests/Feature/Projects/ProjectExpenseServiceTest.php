<?php

namespace Tests\Feature\Projects;

use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Services\ProjectExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-16: ProjectExpenseService aggregates in SQL (SUM and GROUP BY), never loading every row.
 */
class ProjectExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectExpenseService $expenses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expenses = app(ProjectExpenseService::class);
    }

    public function testRunningExpensesEqualLaborPlusMaterialsExactly(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->create(); // ₱360.00 (PRD 7.1)
        ProjectMaterialEntry::factory()->for($project)->create(); // ₱3,600.00 (PRD 7.2)

        $this->assertSame(
            ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'],
            $this->expenses->totals($project),
        );
    }

    public function testOnlyClockedOutAndAdjustedAttendanceCountsAsLabor(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->create(); // closed, 360.00
        Attendance::factory()->for($project)->create(['status' => Attendance::STATUS_ADJUSTED, 'labor_cost' => '100.00']);
        Attendance::factory()->for($project)->open()->create(['labor_cost' => '500.00']);
        Attendance::factory()->for($project)->flagged()->create(['labor_cost' => '500.00']);
        Attendance::factory()->for($project)->void()->create();
        Attendance::factory()->for($project)->create()->delete(); // archived by a correction

        $this->assertSame('460.00', $this->expenses->totals($project)['labor']);
    }

    public function testArchivedMaterialEntriesAndOtherProjectsAreLeftOut(): void
    {
        $project = Project::factory()->ongoing()->create();
        ProjectMaterialEntry::factory()->for($project)->create();
        ProjectMaterialEntry::factory()->for($project)->create()->delete();
        ProjectMaterialEntry::factory()->create();
        Attendance::factory()->create();

        $this->assertSame(
            ['labor' => '0.00', 'materials' => '3600.00', 'running' => '3600.00'],
            $this->expenses->totals($project),
        );
    }

    public function testTheDateRangeFiltersLaborAndMaterials(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->onDate('2026-10-19')->create();
        Attendance::factory()->for($project)->onDate('2026-10-20')->create();
        ProjectMaterialEntry::factory()->for($project)->create(['entry_date' => '2026-10-19']);
        ProjectMaterialEntry::factory()->for($project)->create(['entry_date' => '2026-10-21']);

        $this->assertSame(
            ['labor' => '720.00', 'materials' => '7200.00', 'running' => '7920.00'],
            $this->expenses->totals($project),
        );
        $this->assertSame(
            ['labor' => '360.00', 'materials' => '0.00', 'running' => '360.00'],
            $this->expenses->totals($project, '2026-10-20', '2026-10-20'),
        );
        $this->assertSame(
            ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'],
            $this->expenses->totals($project, null, '2026-10-19'),
        );
        $this->assertSame(
            ['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00'],
            $this->expenses->totals($project, '2026-10-20'),
        );
    }

    public function testLaborByWorkerShowsHoursAndCostPerWorker(): void
    {
        $project = Project::factory()->ongoing()->create();
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $ben = User::factory()->worker()->create(['first_name' => 'Ben', 'last_name' => 'Abad']);

        // Ana: morning and afternoon, 4.50 h each at ₱80.00 → 9.00 h, ₱720.00 (PRD 7.1).
        Attendance::factory()->for($project)->for($ana)->onDate('2026-10-19')->create();
        Attendance::factory()->for($project)->for($ana)->onDate('2026-10-20')->create();
        // Ben: overnight 8.00 h at ₱95.50 → ₱764.00 (PRD 10.2).
        Attendance::factory()->for($project)->for($ben)->onDate('2026-10-20')->create(['hourly_rate' => '95.50', 'minutes_worked' => 480, 'hours_worked' => '8.00', 'labor_cost' => '764.00']);
        Attendance::factory()->for($project)->for($ben)->void()->create();

        $rows = $this->expenses->laborByWorker($project, null, null, 10)->items();

        $this->assertSame(['Abad', 'Cruz'], array_map(fn ($row) => $row['user']->last_name, $rows));
        $this->assertSame(['8.00', '9.00'], array_column($rows, 'hours'));
        $this->assertSame(['764.00', '720.00'], array_column($rows, 'cost'));
        $this->assertSame([1, 2], array_column($rows, 'attendances'));
    }

    public function testLaborByDateIsNewestFirst(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->onDate('2026-10-19')->create();
        Attendance::factory()->for($project)->onDate('2026-10-20')->count(2)->create();

        $rows = $this->expenses->laborByDate($project, null, null, 10)->items();

        $this->assertSame(['2026-10-20', '2026-10-19'], array_map(fn ($row) => $row['date']->toDateString(), $rows));
        $this->assertSame(['9.00', '4.50'], array_column($rows, 'hours'));
        $this->assertSame(['720.00', '360.00'], array_column($rows, 'cost'));
        $this->assertSame([2, 1], array_column($rows, 'workers'));
    }

    public function testMaterialsByItemShowQuantityUnitAndTotal(): void
    {
        $project = Project::factory()->ongoing()->create();
        $blocks = Material::factory()->create(['name' => 'Hollow blocks', 'unit' => 'pc']);
        $sand = Material::factory()->create(['name' => 'Washed sand', 'unit' => 'cu.m']);
        ProjectMaterialEntry::factory()->for($project)->for($blocks)->count(2)->create();
        // 3.5 × ₱12.50 = 43.75 + 8.75 markup = ₱52.50 (PRD 10.3).
        ProjectMaterialEntry::factory()->for($project)->for($sand)->create(['quantity' => '3.5', 'unit_price' => '12.50']);

        $rows = $this->expenses->materialsByItem($project, null, null, 10)->items();

        $this->assertSame(['Hollow blocks', 'Washed sand'], array_map(fn ($row) => $row['material']->name, $rows));
        $this->assertSame(['200.00', '3.50'], array_column($rows, 'quantity'));
        $this->assertSame(['pc', 'cu.m'], array_map(fn ($row) => $row['material']->unit, $rows));
        $this->assertSame(['7200.00', '52.50'], array_column($rows, 'total'));
    }

    public function testMaterialsByDateIsNewestFirst(): void
    {
        $project = Project::factory()->ongoing()->create();
        ProjectMaterialEntry::factory()->for($project)->create(['entry_date' => '2026-10-19']);
        ProjectMaterialEntry::factory()->for($project)->count(2)->create(['entry_date' => '2026-10-21']);

        $rows = $this->expenses->materialsByDate($project, null, null, 10)->items();

        $this->assertSame(['2026-10-21', '2026-10-19'], array_map(fn ($row) => $row['date']->toDateString(), $rows));
        $this->assertSame([2, 1], array_column($rows, 'entries'));
        $this->assertSame(['7200.00', '3600.00'], array_column($rows, 'total'));
    }

    public function testBreakdownsRunAFixedNumberOfQueries(): void
    {
        $project = Project::factory()->ongoing()->create();
        foreach (User::factory()->worker()->count(5)->create() as $user) {
            Attendance::factory()->for($project)->for($user)->create();
        }

        DB::enableQueryLog();
        $this->expenses->laborByWorker($project, null, null, 10)->items();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Count, grouped rows and one query for the users: not one per worker.
        $this->assertLessThanOrEqual(3, $queries);
    }

    /**
     * @return array<string, array{string, ?string, ?string}>
     */
    public static function budgetCases(): array
    {
        return [
            'T-16 example' => ['3960.00', '10000.00', '39.6'],
            'none spent' => ['0.00', '10000.00', '0.0'],
            'half-up to one decimal' => ['1.25', '1000.00', '0.1'],
            'exactly at budget' => ['10000.00', '10000.00', '100.0'],
            'over budget' => ['12500.00', '10000.00', '125.0'],
        ];
    }

    #[DataProvider('budgetCases')]
    public function testBudgetPercentageUsed(string $spent, string $budget, string $percent): void
    {
        $this->assertSame($percent, $this->expenses->budgetUsage($spent, $budget)['percent']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function budgetLevels(): array
    {
        return [
            'under 80%' => ['7999.99', 'normal'],
            'at 80%' => ['8000.00', 'warning'],
            'at 99.9%' => ['9990.00', 'warning'],
            'at 100%' => ['10000.00', 'over'],
            'over 100%' => ['15000.00', 'over'],
        ];
    }

    #[DataProvider('budgetLevels')]
    public function testBudgetBarIsAmberAt80AndRedAt100Percent(string $spent, string $level): void
    {
        $this->assertSame($level, $this->expenses->budgetUsage($spent, '10000.00')['level']);
    }

    public function testTheBarWidthStopsAt100Percent(): void
    {
        $this->assertSame(39, $this->expenses->budgetUsage('3960.00', '10000.00')['width']);
        $this->assertSame(100, $this->expenses->budgetUsage('15000.00', '10000.00')['width']);
    }

    public function testTheDefaultRangeRunsFromTheProjectStartUntilToday(): void
    {
        $this->travelTo('2026-10-21 15:00:00');

        $this->assertSame(
            ['from' => '2026-10-01', 'to' => '2026-10-21'],
            $this->expenses->defaultRange(Project::factory()->make(['start_date' => '2026-10-01'])),
        );
        $this->assertSame(
            ['from' => '2026-10-21', 'to' => '2026-10-21'],
            $this->expenses->defaultRange(Project::factory()->make(['start_date' => '2026-10-21'])),
        );
    }

    public function testNoDefaultRangeWithoutAStartDateOrBeforeTheProjectStarts(): void
    {
        $this->travelTo('2026-10-21 15:00:00');

        $this->assertNull($this->expenses->defaultRange(Project::factory()->make(['start_date' => null])));
        $this->assertNull($this->expenses->defaultRange(Project::factory()->make(['start_date' => '2026-11-01'])));
    }

    public function testLaborAndMaterialsShareOfRunningExpenses(): void
    {
        $this->assertSame(
            ['labor' => '9.1', 'materials' => '90.9'],
            $this->expenses->shares(['labor' => '360.00', 'materials' => '3600.00', 'running' => '3960.00']),
        );
        $this->assertSame(
            ['labor' => '100.0', 'materials' => '0.0'],
            $this->expenses->shares(['labor' => '360.00', 'materials' => '0.00', 'running' => '360.00']),
        );
        $this->assertSame(
            ['labor' => '0.0', 'materials' => '0.0'],
            $this->expenses->shares(['labor' => '0.00', 'materials' => '0.00', 'running' => '0.00']),
        );
    }

    public function testRangeDropsInvalidDatesAndSwapsFromAndTo(): void
    {
        $this->assertSame(['', ''], $this->expenses->range('2026-02-31', 'x'));
        $this->assertSame(['2026-10-01', '2026-10-31'], $this->expenses->range('2026-10-31', '2026-10-01'));
        $this->assertSame(['2026-10-01', ''], $this->expenses->range('2026-10-01', ''));
    }

    public function testAnImpossibleDateIsIgnoredByTheTotals(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->onDate('2026-10-20')->create();

        $this->assertSame('360.00', $this->expenses->totals($project, '2026-02-31')['labor']);
    }

    public function testNoBudgetMeansNoUsage(): void
    {
        $this->assertNull($this->expenses->budgetUsage('3960.00', null));
        $this->assertNull($this->expenses->budgetUsage('3960.00', '0.00'));
    }
}
