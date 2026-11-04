<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserShift;
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

    /**
     * One shift a day, Monday to Saturday, Sunday as rest day (T-11.2).
     */
    public function oneShift(string $in = '08:00', string $out = '17:00'): static
    {
        return $this->withShift(User::SHIFT_ONE, [$in, $out, null, null]);
    }

    /**
     * Two shifts a day (morning and afternoon), Monday to Saturday, Sunday as rest day (T-11.2).
     */
    public function twoShifts(string $morningIn = '08:00', string $morningOut = '12:00', string $afternoonIn = '13:00', string $afternoonOut = '17:00'): static
    {
        return $this->withShift(User::SHIFT_TWO, [$morningIn, $morningOut, $afternoonIn, $afternoonOut]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    /**
     * @param  array{0: string, 1: string, 2: ?string, 3: ?string}  $times
     */
    private function withShift(string $type, array $times): static
    {
        return $this->afterCreating(function (User $user) use ($type, $times) {
            $user->forceFill(['shift_type' => $type])->save();

            foreach (range(1, 7) as $weekday) {
                $rest = $weekday === 7;

                (new UserShift)->forceFill([
                    'user_id' => $user->id,
                    'weekday' => $weekday,
                    'is_rest_day' => $rest,
                    'first_in' => $rest ? null : $times[0],
                    'first_out' => $rest ? null : $times[1],
                    'second_in' => $rest ? null : $times[2],
                    'second_out' => $rest ? null : $times[3],
                ])->save();
            }
        });
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
