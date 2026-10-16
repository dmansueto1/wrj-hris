<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private UserRateService $rates) {}

    /**
     * Create a user with a temporary password they must change at first login.
     * The username is generated and the first rate-history row is written by the User model.
     *
     * @param  array{employee_no: string, first_name: string, last_name: string, email: ?string, role: string, position: ?string, hourly_rate: string, password: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User;
            $user->forceFill([
                'employee_no' => $data['employee_no'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?: null,
                'position' => $data['position'] ?: null,
                'hourly_rate' => $data['hourly_rate'],
                'password' => $data['password'],
                'must_change_password' => true,
                'status' => User::STATUS_ACTIVE,
            ])->save();

            $user->assignRole($data['role']);

            return $user;
        });
    }

    /**
     * Update a user's details. A changed hourly rate goes through UserRateService
     * so it is recorded in the rate history.
     *
     * @param  array{employee_no: string, first_name: string, last_name: string, email: ?string, role: string, position: ?string, hourly_rate: string}  $data
     */
    public function update(User $user, array $data, User $editor): void
    {
        if ($user->is($editor) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages(['role' => __('You cannot remove the admin role from your own account.')]);
        }

        DB::transaction(function () use ($user, $data, $editor) {
            $user->forceFill([
                'employee_no' => $data['employee_no'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?: null,
                'position' => $data['position'] ?: null,
            ])->save();

            $user->syncRoles([$data['role']]);

            $this->rates->changeRate($user, $data['hourly_rate'], $editor);
        });
    }

    public function deactivate(User $user, User $editor): void
    {
        $this->guardAgainstSelf($user, $editor, __('You cannot deactivate your own account.'));

        $user->forceFill(['status' => User::STATUS_INACTIVE])->save();
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
    }

    /**
     * Set a new temporary password the user must change at their next login.
     */
    public function resetPassword(User $user, string $temporaryPassword): void
    {
        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ])->save();
    }

    /**
     * Archive (soft-delete) the user. Their records stay intact.
     */
    public function archive(User $user, User $editor): void
    {
        $this->guardAgainstSelf($user, $editor, __('You cannot archive your own account.'));

        $user->delete();
    }

    private function guardAgainstSelf(User $user, User $editor, string $message): void
    {
        if ($user->is($editor)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
