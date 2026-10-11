<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_no' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'position' => fake()->randomElement(['Mason', 'Carpenter', 'Laborer', 'Helper']),
            'hourly_rate' => '80.00',
            'status' => User::STATUS_ACTIVE,
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function staff(): static
    {
        return $this->withRole('staff');
    }

    public function worker(): static
    {
        return $this->withRole('worker');
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    private function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            if (! Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                (new RolesAndPermissionsSeeder)->run();
            }

            $user->assignRole($role);
        });
    }
}
