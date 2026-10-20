<?php

namespace App\Livewire\Materials\Concerns;

use App\Models\Material;
use App\Services\MaterialService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Fields, validation and permission check shared by the create and edit material screens (T-08).
 */
trait MaterialForm
{
    public string $code = '';

    public string $name = '';

    public string $unit = 'pcs';

    public string $default_price = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('materials.manage'), 403);
    }

    /**
     * Validate the form. The code is uppercased first so "hb-04" and "HB-04" count as the same code.
     *
     * @return array{code: string, name: string, unit: string, default_price: string}
     */
    protected function validateMaterial(?int $ignoreMaterialId = null): array
    {
        $this->code = MaterialService::normalizeCode($this->code);

        /** @var array{code: string, name: string, unit: string, default_price: string} $validated */
        $validated = $this->validate([
            // Archived materials keep their code, so the unique check includes them.
            'code' => ['required', 'string', 'max:30', Rule::unique(Material::class)->ignore($ignoreMaterialId)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::in(Material::UNITS)],
            // 0 or more, at most two decimals, fits DECIMAL(12,2).
            'default_price' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ], [
            'default_price.regex' => __('The default price must be 0 or more, with at most two decimals.'),
        ]);

        return $validated;
    }

    protected function fillFromMaterial(Material $material): void
    {
        $this->code = $material->code;
        $this->name = $material->name;
        $this->unit = $material->unit;
        $this->default_price = $material->default_price;
    }
}
