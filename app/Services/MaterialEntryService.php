<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Support\Costing\MaterialCost;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Material purchases per project (T-15, PRD US-30). Amounts are always computed by
 * MaterialCost: the model fills them on save, and the form preview uses the same calculation.
 */
class MaterialEntryService
{
    /** Projects that can receive new material entries. */
    public const PICKABLE_PROJECT_STATUSES = [Project::STATUS_ONGOING, Project::STATUS_ON_HOLD];

    /**
     * @param  array{entry_date: string, project_id: int|string, material_id: int|string, quantity: string, unit_price: string, remarks: ?string}  $data
     */
    public function create(User $enteredBy, array $data): ProjectMaterialEntry
    {
        $entry = new ProjectMaterialEntry($this->attributes($data));
        $entry->entered_by = $enteredBy->id;
        $entry->save();

        return $entry;
    }

    /**
     * The saved markup rate is kept, so only the quantity or price can change the amounts.
     *
     * @param  array{entry_date: string, project_id: int|string, material_id: int|string, quantity: string, unit_price: string, remarks: ?string}  $data
     */
    public function update(ProjectMaterialEntry $entry, array $data): void
    {
        $entry->fill($this->attributes($data))->save();
    }

    /**
     * Archive (soft-delete) the entry; it no longer counts toward the project's expenses.
     */
    public function archive(ProjectMaterialEntry $entry): void
    {
        $entry->delete();
    }

    /**
     * Admin may change any entry; Staff only their own entries made today (Philippine time).
     */
    public function canChange(User $user, ProjectMaterialEntry $entry): bool
    {
        if (! $user->can('material-entries.create')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $entry->entered_by === $user->id
            && $entry->created_at !== null
            && $entry->created_at->copy()->timezone(config('app.timezone'))->isToday();
    }

    /**
     * Subtotal, markup and total for the form, or null while the inputs are not valid amounts.
     *
     * @return array{subtotal: string, markup: string, total: string}|null
     */
    public function preview(string $unitPrice, string $quantity, string $markupRate): ?array
    {
        if (! self::isAmount($unitPrice) || ! self::isAmount($quantity)) {
            return null;
        }

        try {
            return MaterialCost::compute($unitPrice, $quantity, $markupRate);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The markup rate a new entry will be saved with, e.g. "0.20".
     */
    public function currentMarkupRate(): string
    {
        return Settings::string('material_markup_rate');
    }

    /**
     * Projects offered in the form. The entry's own project stays offered when editing,
     * even if it is no longer Ongoing or On hold.
     *
     * @return Collection<int, Project>
     */
    public function pickableProjects(?int $keepProjectId = null): Collection
    {
        // withTrashed so the kept project is offered even if it was archived since.
        return Project::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereIn('status', self::PICKABLE_PROJECT_STATUSES)->whereNull('deleted_at'))
                ->when($keepProjectId, fn (Builder $query) => $query->orWhere('id', $keepProjectId)))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);
    }

    /**
     * Active materials offered in the form, plus the entry's own material when editing.
     *
     * @return Collection<int, Material>
     */
    public function pickableMaterials(?int $keepMaterialId = null): Collection
    {
        // withTrashed so the kept material is offered even if it was archived since.
        return Material::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('status', Material::STATUS_ACTIVE)->whereNull('deleted_at'))
                ->when($keepMaterialId, fn (Builder $query) => $query->orWhere('id', $keepMaterialId)))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'default_price']);
    }

    /**
     * Entries matching the list filters, newest first.
     *
     * @param  array{project?: string, material?: string, from?: string, to?: string}  $filters
     * @return Builder<ProjectMaterialEntry>
     */
    public function filtered(array $filters): Builder
    {
        return ProjectMaterialEntry::query()
            ->when(($filters['project'] ?? '') !== '', fn (Builder $query) => $query->where('project_id', (int) $filters['project']))
            ->when(($filters['material'] ?? '') !== '', fn (Builder $query) => $query->where('material_id', (int) $filters['material']))
            ->when(self::isDate($filters['from'] ?? ''), fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['from']))
            ->when(self::isDate($filters['to'] ?? ''), fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['to']));
    }

    /**
     * Totals of all entries matching the filters (every page), summed by the database on
     * DECIMAL values and normalized through centavos.
     *
     * @param  array{project?: string, material?: string, from?: string, to?: string}  $filters
     * @return array{count: int, subtotal: string, markup: string, total: string}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('count(*) as entries, coalesce(sum(subtotal), 0) as subtotal, coalesce(sum(markup_amount), 0) as markup, coalesce(sum(total_cost), 0) as total')
            ->first();

        $normalize = fn ($value) => Money::fromCentavos(Money::toCentavos((string) $value));

        return [
            'count' => (int) $row->entries,
            'subtotal' => $normalize($row->subtotal),
            'markup' => $normalize($row->markup),
            'total' => $normalize($row->total),
        ];
    }

    /**
     * @param  array{entry_date: string, project_id: int|string, material_id: int|string, quantity: string, unit_price: string, remarks: ?string}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $remarks = trim((string) ($data['remarks'] ?? ''));

        return [
            'entry_date' => $data['entry_date'],
            'project_id' => (int) $data['project_id'],
            'material_id' => (int) $data['material_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'remarks' => $remarks === '' ? null : $remarks,
        ];
    }

    /**
     * 0 or more with at most two decimals, the format the DECIMAL(12,2) columns hold.
     */
    public static function isAmount(string $value): bool
    {
        return preg_match('/^\d{1,10}(\.\d{1,2})?$/', trim($value)) === 1;
    }

    private static function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
