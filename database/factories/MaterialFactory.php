<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $unit] = fake()->randomElement([
            ['Hollow blocks 4"', 'pcs'],
            ['Portland cement 40kg', 'bags'],
            ['Washed sand', 'cu.m'],
            ['Gravel 3/4"', 'cu.m'],
            ['Deformed bar 10mm', 'pcs'],
            ['Tie wire #16', 'kg'],
            ['Plywood 1/2"', 'sheets'],
            ['Paint, latex white', 'L'],
        ]);

        return [
            'code' => 'MAT-'.fake()->unique()->numerify('####'),
            'name' => $name,
            'unit' => $unit,
            'default_price' => fake()->numberBetween(10, 1500).'.00',
            'status' => Material::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Material::STATUS_INACTIVE]);
    }
}
