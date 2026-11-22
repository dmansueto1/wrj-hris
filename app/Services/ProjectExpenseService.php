<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Running expenses of a project (T-16, PRD 7.3 and US-31): labor of counted attendance (Clocked Out
 * or Adjusted) plus material entries. Every figure is a SUM / GROUP BY in SQL on the DECIMAL
 * columns, normalized through centavos; rows are never loaded one by one. Each method takes an
 * optional date range on the work date (labor) or entry date (materials), Y-m-d, both ends included.
 */
class ProjectExpenseService
{
    /** The budget bar turns amber at 80% used and red at 100%. */
    public const WARNING_PERCENT = 80;

    /**
     * @return array{labor: string, materials: string, running: string}
     */
    public function totals(Project $project, ?string $from = null, ?string $to = null): array
    {
        return $this->sumTotals($project, $from, $to);
    }

    /**
     * Running expenses of every project together (the Dashboard's "Expenses this month"). Labor and
     * purchases on archived projects still count: the money was spent.
     *
     * @return array{labor: string, materials: string, running: string}
     */
    public function overallTotals(?string $from = null, ?string $to = null): array
    {
        return $this->sumTotals(null, $from, $to);
    }

    /**
     * How many projects (archived ones left out) have used at least WARNING_PERCENT of their budget,
     * and how many 100% or more, all-time. Counted by the database, one query each.
     *
     * @return array{warning: int, over: int}
     */
    public function budgetWatch(): array
    {
        return [
            'warning' => $this->whereBudgetUsedAtLeast(Project::query(), self::WARNING_PERCENT)->count(),
            'over' => $this->whereBudgetUsedAtLeast(Project::query(), 100)->count(),
        ];
    }

    /**
     * Keeps projects with a budget whose all-time running expenses are at least $percent of it:
     * (counted labor + materials) × 100 >= budget × percent, exact DECIMAL arithmetic in SQL.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function whereBudgetUsedAtLeast(Builder $query, int $percent): Builder
    {
        $labor = $this->labor(null, null, null)->toBase()
            ->whereColumn('attendances.project_id', 'projects.id')
            ->selectRaw('COALESCE(SUM(attendances.labor_cost), 0)');
        $materials = $this->materials(null, null, null)->toBase()
            ->whereColumn('project_material_entries.project_id', 'projects.id')
            ->selectRaw('COALESCE(SUM(project_material_entries.total_cost), 0)');

        return $query
            ->whereNotNull('projects.budget')
            ->where('projects.budget', '>', 0)
            ->whereRaw(
                '(('.$labor->toSql().') + ('.$materials->toSql().')) * 100 >= projects.budget * ?',
                [...$labor->getBindings(), ...$materials->getBindings(), $percent],
            );
    }

    /**
     * @return array{labor: string, materials: string, running: string}
     */
    private function sumTotals(?Project $project, ?string $from, ?string $to): array
    {
        $labor = Money::toCentavos(self::decimal2($this->labor($project, $from, $to)->sum('labor_cost')));
        $materials = Money::toCentavos(self::decimal2($this->materials($project, $from, $to)->sum('total_cost')));

        return [
            'labor' => Money::fromCentavos($labor),
            'materials' => Money::fromCentavos($materials),
            'running' => Money::fromCentavos($labor + $materials),
        ];
    }

    /**
     * Hours and labor cost per employee, by last name.
     *
     * @return LengthAwarePaginator<int, array{user: User, attendances: int, hours: string, cost: string}>
     */
    public function laborByWorker(Project $project, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $page = $this->labor($project, $from, $to)
            ->toBase()
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->groupBy('attendances.user_id', 'users.last_name', 'users.first_name')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->orderBy('attendances.user_id')
            ->selectRaw('attendances.user_id, COUNT(*) AS attendances, COALESCE(SUM(attendances.hours_worked), 0) AS hours, COALESCE(SUM(attendances.labor_cost), 0) AS cost')
            ->paginate($perPage);

        // One query for the employees on this page (archived ones too, so past labor keeps its name).
        $users = User::withTrashed()->whereIn('id', collect($page->items())->pluck('user_id'))->get()->keyBy('id');

        return $page->through(fn (object $row) => [
            'user' => $users[$row->user_id],
            'attendances' => (int) $row->attendances,
            'hours' => self::decimal2($row->hours),
            'cost' => self::decimal2($row->cost),
        ]);
    }

    /**
     * Hours, labor cost and number of employees per work date, newest first.
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, workers: int, hours: string, cost: string}>
     */
    public function laborByDate(Project $project, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        return $this->labor($project, $from, $to)
            ->toBase()
            ->groupBy('work_date')
            ->orderByDesc('work_date')
            ->selectRaw('work_date, COUNT(DISTINCT user_id) AS workers, COALESCE(SUM(hours_worked), 0) AS hours, COALESCE(SUM(labor_cost), 0) AS cost')
            ->paginate($perPage)
            ->through(fn (object $row) => [
                'date' => Carbon::parse($row->work_date),
                'workers' => (int) $row->workers,
                'hours' => self::decimal2($row->hours),
                'cost' => self::decimal2($row->cost),
            ]);
    }

    /**
     * Quantity bought and total cost (markup included) per material, by name.
     *
     * @return LengthAwarePaginator<int, array{material: Material, entries: int, quantity: string, total: string}>
     */
    public function materialsByItem(Project $project, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $page = $this->materials($project, $from, $to)
            ->toBase()
            ->join('materials', 'materials.id', '=', 'project_material_entries.material_id')
            ->groupBy('project_material_entries.material_id', 'materials.name')
            ->orderBy('materials.name')
            ->orderBy('project_material_entries.material_id')
            ->selectRaw('project_material_entries.material_id, COUNT(*) AS entries, COALESCE(SUM(project_material_entries.quantity), 0) AS quantity, COALESCE(SUM(project_material_entries.total_cost), 0) AS total')
            ->paginate($perPage);

        $materials = Material::withTrashed()->whereIn('id', collect($page->items())->pluck('material_id'))->get()->keyBy('id');

        return $page->through(fn (object $row) => [
            'material' => $materials[$row->material_id],
            'entries' => (int) $row->entries,
            'quantity' => self::decimal2($row->quantity),
            'total' => self::decimal2($row->total),
        ]);
    }

    /**
     * Number of entries and total cost per purchase date, newest first.
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, entries: int, total: string}>
     */
    public function materialsByDate(Project $project, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        return $this->materials($project, $from, $to)
            ->toBase()
            ->groupBy('entry_date')
            ->orderByDesc('entry_date')
            ->selectRaw('entry_date, COUNT(*) AS entries, COALESCE(SUM(total_cost), 0) AS total')
            ->paginate($perPage)
            ->through(fn (object $row) => [
                'date' => Carbon::parse($row->entry_date),
                'entries' => (int) $row->entries,
                'total' => self::decimal2($row->total),
            ]);
    }

    /**
     * How much of the budget is used: the percentage to one decimal, half-up ("39.6"), the bar
     * width (whole percent, at most 100) and the bar level: "normal", "warning" from 80%, "over"
     * from 100%. Null when the project has no budget (or a zero budget). Whole centavos, no floats.
     *
     * @return array{percent: string, width: int, level: string}|null
     */
    public function budgetUsage(string $spent, ?string $budget): ?array
    {
        if ($budget === null || Money::toCentavos($budget) <= 0) {
            return null;
        }

        $spent = Money::toCentavos($spent);
        $budget = Money::toCentavos($budget);

        return [
            'percent' => self::percent($spent, $budget),
            'width' => min(100, intdiv($spent * 100, $budget)),
            'level' => match (true) {
                $spent >= $budget => 'over',
                $spent * 100 >= $budget * self::WARNING_PERCENT => 'warning',
                default => 'normal',
            },
        ];
    }

    /**
     * Each part's share of running expenses, to one decimal, half-up: ₱360.00 labor and ₱3,600.00
     * materials give labor "9.1" and materials "90.9". Both "0.0" when nothing is spent yet.
     *
     * @param  array{labor: string, materials: string, running: string}  $totals
     * @return array{labor: string, materials: string}
     */
    public function shares(array $totals): array
    {
        $running = Money::toCentavos($totals['running']);

        if ($running <= 0) {
            return ['labor' => '0.0', 'materials' => '0.0'];
        }

        return [
            'labor' => self::percent(Money::toCentavos($totals['labor']), $running),
            'materials' => self::percent(Money::toCentavos($totals['materials']), $running),
        ];
    }

    /**
     * A From / To pair as typed on the project page: anything that is not a real Y-m-d date becomes
     * '', and From and To are swapped when From is later.
     *
     * @return array{string, string}
     */
    public function range(string $from, string $to): array
    {
        $from = self::isDate($from) ? $from : '';
        $to = self::isDate($to) ? $to : '';

        return $from !== '' && $to !== '' && $from > $to ? [$to, $from] : [$from, $to];
    }

    /**
     * The project page's date range when none is chosen: from the project's start date until today.
     * Null (no range, everything counts) when the project has no start date or has not started yet.
     *
     * @return array{from: string, to: string}|null
     */
    public function defaultRange(Project $project): ?array
    {
        $today = now()->startOfDay();

        if ($project->start_date === null || $project->start_date->greaterThan($today)) {
            return null;
        }

        return ['from' => $project->start_date->toDateString(), 'to' => $today->toDateString()];
    }

    /**
     * Adds `labor_total` and `materials_total` sums to a projects query, one subquery each, so a
     * list can show running expenses without a query per project (see runningExpensesOf()).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function withTotals(Builder $query): Builder
    {
        return $query
            ->withSum(['attendances as labor_total' => fn (Builder $attendances) => $attendances->counted()], 'labor_cost')
            ->withSum('materialEntries as materials_total', 'total_cost');
    }

    /**
     * Running expenses of a project loaded through withTotals(), e.g. "3960.00".
     */
    public function runningExpensesOf(Project $project): string
    {
        return Money::fromCentavos(
            Money::toCentavos(self::decimal2($project->getAttribute('labor_total')))
            + Money::toCentavos(self::decimal2($project->getAttribute('materials_total')))
        );
    }

    /**
     * Counted attendance of the project (every project when null) in the date range (archived corrections left out).
     *
     * @return Builder<Attendance>
     */
    private function labor(?Project $project, ?string $from, ?string $to): Builder
    {
        return Attendance::query()
            ->when($project !== null, fn (Builder $query) => $query->where('attendances.project_id', $project?->id))
            ->whereIn('attendances.status', Attendance::COUNTED_STATUSES)
            ->when(self::isDate($from), fn (Builder $query) => $query->where('attendances.work_date', '>=', $from))
            ->when(self::isDate($to), fn (Builder $query) => $query->where('attendances.work_date', '<=', $to));
    }

    /**
     * Material entries of the project (every project when null) in the date range (archived entries left out).
     *
     * @return Builder<ProjectMaterialEntry>
     */
    private function materials(?Project $project, ?string $from, ?string $to): Builder
    {
        return ProjectMaterialEntry::query()
            ->when($project !== null, fn (Builder $query) => $query->where('project_material_entries.project_id', $project?->id))
            ->when(self::isDate($from), fn (Builder $query) => $query->where('project_material_entries.entry_date', '>=', $from))
            ->when(self::isDate($to), fn (Builder $query) => $query->where('project_material_entries.entry_date', '<=', $to));
    }

    /**
     * A database DECIMAL(…, 2) sum ("3960.00", "3960", null) as a two-decimal string, for pesos and hours.
     */
    private static function decimal2(mixed $value): string
    {
        return Money::fromCentavos(Money::toCentavos((string) ($value ?? '0') ?: '0'));
    }

    /**
     * $part of $whole (centavos, $whole > 0) as a percentage to one decimal, half-up, without
     * floats: 396000 of 1000000 → 396 tenths → "39.6".
     */
    private static function percent(int $part, int $whole): string
    {
        $tenths = intdiv($part * 1000 * 2 + $whole, $whole * 2);

        return intdiv($tenths, 10).'.'.($tenths % 10);
    }

    private static function isDate(?string $value): bool
    {
        return $value !== null
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
