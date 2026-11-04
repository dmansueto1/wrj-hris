<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    /** The shift every new user starts with (T-11.2); changed on the user's page. */
    public const NEW_USER_SHIFT = User::SHIFT_TWO;

    public function __construct(private UserRateService $rates, private UserShiftService $shifts) {}

    /**
     * Create a user with a temporary password they must change at first login.
     * The username is generated and the first rate-history row is written by the User model.
     * The shift chosen on Add user is saved with the user (T-11.2); without one, the user starts
     * with Two shifts a day: Monday to Saturday 08:00–12:00 and 13:00–17:00, Sunday as rest day.
     *
     * @param  array{employee_no: string, first_name: string, last_name: string, email: ?string, role: string, position: ?string, hourly_rate: string, password: string}  $data
     * @param  array<int|string, array<string, mixed>>|null  $shiftDays
     */
    public function create(array $data, string $shiftType = self::NEW_USER_SHIFT, ?array $shiftDays = null): User
    {
        return DB::transaction(function () use ($data, $shiftType, $shiftDays) {
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
            $this->shifts->startWith($user, $shiftType, $shiftDays);

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

    /**
     * Counts for the stat cards above the users list (archived users excluded).
     *
     * @return array{total: int, active: int, inactive: int, roles: array<string, int>}
     */
    public function summary(): array
    {
        $byStatus = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $roles = [];
        foreach (User::ROLES as $role) {
            $roles[$role] = User::role($role)->count();
        }

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[User::STATUS_ACTIVE] ?? 0),
            'inactive' => (int) ($byStatus[User::STATUS_INACTIVE] ?? 0),
            'roles' => $roles,
        ];
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
