<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMaterialEntry>
 */
class ProjectMaterialEntryFactory extends Factory
{
    /**
     * 100 hollow blocks at ₱30.00 (PRD 7.2 example); the amounts and markup rate are filled on save.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->ongoing(),
            'material_id' => Material::factory(),
            'entry_date' => now()->toDateString(),
            'quantity' => '100',
            'unit_price' => '30.00',
            'remarks' => null,
            'entered_by' => User::factory()->staff(),
        ];
    }
}
