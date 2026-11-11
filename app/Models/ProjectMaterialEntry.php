<?php

namespace App\Models;

use App\Support\Costing\MaterialCost;
use App\Support\Settings;
use Database\Factories\ProjectMaterialEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One material purchase for a project (PRD US-30). The markup rate is saved on the entry and the
 * amounts are computed from it on every save, so later setting changes never alter its cost.
 * `entered_by` and `markup_rate` are never mass-assigned.
 *
 * @property int $id
 * @property int $project_id
 * @property int $material_id
 * @property Carbon $entry_date
 * @property string $quantity
 * @property string $unit_price
 * @property string $markup_rate
 * @property string $subtotal
 * @property string $markup_amount
 * @property string $total_cost
 * @property string|null $remarks
 * @property int $entered_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['project_id', 'material_id', 'entry_date', 'quantity', 'unit_price', 'remarks'])]
class ProjectMaterialEntry extends Model
{
    /** @use HasFactory<ProjectMaterialEntryFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (ProjectMaterialEntry $entry) {
            if ($entry->markup_rate === null || $entry->markup_rate === '') {
                // System Settings; 0.20 (the catalog default) when it was never changed.
                $entry->markup_rate = Settings::string('material_markup_rate');
            }

            $cost = MaterialCost::compute((string) $entry->unit_price, (string) $entry->quantity, (string) $entry->markup_rate);

            $entry->subtotal = $cost['subtotal'];
            $entry->markup_amount = $cost['markup'];
            $entry->total_cost = $cost['total'];
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'markup_rate' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by')->withTrashed();
    }
}
