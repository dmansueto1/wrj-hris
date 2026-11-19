<?php

namespace App\Livewire\MaterialEntries\Concerns;

use App\Models\Material;
use App\Models\Project;
use App\Services\MaterialEntryService;
use App\Support\Settings;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Fields, validation, the live cost preview and the permission check shared by the
 * create and edit material entry screens (T-15).
 */
trait MaterialEntryForm
{
    public string $entry_date = '';

    public string $project_id = '';

    public string $material_id = '';

    public string $quantity = '';

    public string $unit_price = '';

    public string $remarks = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('material-entries.create'), 403);
    }

    /**
     * Picking a material fills the unit price with its default price; it can still be changed.
     */
    public function updatedMaterialId(string $value): void
    {
        $material = $this->materials->firstWhere('id', (int) $value);

        if ($material instanceof Material) {
            $this->unit_price = $material->default_price;
        }
    }

    /**
     * Subtotal, markup and total, computed on the server with MaterialCost.
     *
     * @return array{subtotal: string, markup: string, total: string}|null
     */
    #[Computed]
    public function preview(): ?array
    {
        return app(MaterialEntryService::class)->preview($this->unit_price, $this->quantity, $this->markupRate());
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return app(MaterialEntryService::class)->pickableProjects($this->keptProjectId());
    }

    /**
     * @return Collection<int, Material>
     */
    #[Computed]
    public function materials(): Collection
    {
        return app(MaterialEntryService::class)->pickableMaterials($this->keptMaterialId());
    }

    /**
     * The markup shown as a percent, e.g. "20".
     */
    public function markupPercent(): string
    {
        return Settings::rateToPercent($this->markupRate());
    }

    /**
     * @return array{entry_date: string, project_id: string, material_id: string, quantity: string, unit_price: string, remarks: string}
     */
    protected function validateEntry(): array
    {
        $this->quantity = trim($this->quantity);
        $this->unit_price = trim($this->unit_price);

        /** @var array{entry_date: string, project_id: string, material_id: string, quantity: string, unit_price: string, remarks: string} $validated */
        $validated = $this->validate([
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'project_id' => ['required', Rule::in($this->projects->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'material_id' => ['required', Rule::in($this->materials->pluck('id')->map(fn ($id) => (string) $id)->all())],
            // More than 0, at most two decimals, fits DECIMAL(12,2).
            'quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', function (string $attribute, string $value, Closure $fail) {
                if (! preg_match('/[1-9]/', $value)) {
                    $fail(__('The quantity must be more than 0.'));
                }
            }],
            'unit_price' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', function (string $attribute, string $value, Closure $fail) {
                if (MaterialEntryService::isAmount($this->quantity) && $this->preview === null) {
                    $fail(__('The total cost is too large.'));
                }
            }],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'entry_date.before_or_equal' => __('The date cannot be in the future.'),
            'project_id.in' => __('Choose an Ongoing or On hold project.'),
            'material_id.in' => __('Choose an active material.'),
            'quantity.regex' => __('The quantity must be more than 0, with at most two decimals.'),
            'unit_price.regex' => __('The unit price must be 0 or more, with at most two decimals.'),
        ], [
            'project_id' => __('project'),
            'material_id' => __('material'),
            'unit_price' => __('unit price'),
            'entry_date' => __('date'),
        ]);

        return $validated;
    }

    /**
     * The markup rate the entry is (or will be) saved with.
     */
    abstract protected function markupRate(): string;

    /**
     * A project that stays pickable even if no longer Ongoing / On hold (the edited entry's own).
     */
    protected function keptProjectId(): ?int
    {
        return null;
    }

    protected function keptMaterialId(): ?int
    {
        return null;
    }
}
