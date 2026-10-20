<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Str;

class MaterialService
{
    /**
     * @param  array{code: string, name: string, unit: string, default_price: string}  $data
     */
    public function create(array $data): Material
    {
        $material = new Material;
        $this->fill($material, $data)->save();

        return $material;
    }

    /**
     * @param  array{code: string, name: string, unit: string, default_price: string}  $data
     */
    public function update(Material $material, array $data): void
    {
        $this->fill($material, $data)->save();
    }

    /**
     * An inactive material stays in past entries but cannot be picked for new ones.
     */
    public function deactivate(Material $material): void
    {
        $material->forceFill(['status' => Material::STATUS_INACTIVE])->save();
    }

    public function reactivate(Material $material): void
    {
        $material->forceFill(['status' => Material::STATUS_ACTIVE])->save();
    }

    /**
     * Archive (soft-delete) the material. Past material entries stay intact.
     */
    public function archive(Material $material): void
    {
        $material->delete();
    }

    /**
     * Counts for the stat cards above the materials list (archived materials excluded).
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function summary(): array
    {
        $byStatus = Material::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[Material::STATUS_ACTIVE] ?? 0),
            'inactive' => (int) ($byStatus[Material::STATUS_INACTIVE] ?? 0),
        ];
    }

    /**
     * Codes are stored in uppercase ("hb-04" becomes "HB-04").
     */
    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * @param  array{code: string, name: string, unit: string, default_price: string}  $data
     */
    private function fill(Material $material, array $data): Material
    {
        return $material->forceFill([
            'code' => self::normalizeCode($data['code']),
            'name' => $data['name'],
            'unit' => $data['unit'],
            'default_price' => $data['default_price'],
        ]);
    }
}
