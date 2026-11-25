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
            $oldRole = (string) $user->getRoleNames()->first();

            $user->forceFill([
                'employee_no' => $data['employee_no'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?: null,
                'position' => $data['position'] ?: null,
            ])->save();

            $user->syncRoles([$data['role']]);

            if ($oldRole !== $data['role']) {
                $this->log($user, $editor, 'Changed role', ['role' => $oldRole], ['role' => $data['role']]);
            }

            $this->rates->changeRate($user, $data['hourly_rate'], $editor);
        });
    }

    public function deactivate(User $user, User $editor): void
    {
        $this->guardAgainstSelf($user, $editor, __('You cannot deactivate your own account.'));

        $this->changeStatus($user, User::STATUS_INACTIVE, $editor, 'Deactivated user');
    }

    public function reactivate(User $user, ?User $editor = null): void
    {
        $this->changeStatus($user, User::STATUS_ACTIVE, $editor, 'Reactivated user');
    }

    /**
     * Set a new temporary password the user must change at their next login. The activity log
     * records that it happened and who did it, never the password (T-17).
     */
    public function resetPassword(User $user, string $temporaryPassword, ?User $editor = null): void
    {
        DB::transaction(function () use ($user, $temporaryPassword, $editor) {
            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            $this->log($user, $editor, 'Reset password');
        });
    }

    /**
     * Archive (soft-delete) the user. Their records stay intact.
     */
    public function archive(User $user, User $editor): void
    {
        $this->guardAgainstSelf($user, $editor, __('You cannot archive your own account.'));

        $user->delete();
    }

    private function changeStatus(User $user, string $status, ?User $editor, string $description): void
    {
        $old = $user->status;

        DB::transaction(function () use ($user, $status, $editor, $description, $old) {
            $user->forceFill(['status' => $status])->save();

            if ($old !== $status) {
                $this->log($user, $editor, $description, ['status' => $old], ['status' => $status]);
            }
        });
    }

    /**
     * T-17: one entry in the "users" log. Only the given fields are written, so a password or
     * remember token can never end up in the activity log.
     *
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     */
    private function log(User $user, ?User $editor, string $description, array $old = [], array $new = []): void
    {
        $activity = activity('users')->performedOn($user);

        if ($editor !== null) {
            $activity->causedBy($editor);
        }

        if ($old !== [] || $new !== []) {
            $activity->withProperties(['old' => $old, 'new' => $new]);
        }

        $activity->log($description);
    }

    private function guardAgainstSelf(User $user, User $editor, string $message): void
    {
        if ($user->is($editor)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
