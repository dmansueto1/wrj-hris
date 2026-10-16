<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+1 month');

        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('####'),
            'name' => fake()->randomElement(['Riverside', 'Hilltop', 'Bayview', 'Greenfield', 'Sunrise']).' '.fake()->randomElement(['Townhomes', 'Warehouse', 'Clinic', 'School Building', 'Mall Annex']),
            'location' => fake()->randomElement(['Pasig City', 'Taguig City', 'Quezon City', 'Antipolo', 'Cavite']),
            'client_name' => fake()->company(),
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween($start, '+1 year'),
            'budget' => fake()->numberBetween(500, 50000).'000.00',
            'status' => Project::STATUS_ONGOING,
        ];
    }

    public function planning(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Project::STATUS_PLANNING]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Project::STATUS_ONGOING]);
    }

    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Project::STATUS_ON_HOLD]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Project::STATUS_COMPLETED]);
    }
}
