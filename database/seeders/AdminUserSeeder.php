<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Random\Randomizer;

class AdminUserSeeder extends Seeder
{
    public const EMPLOYEE_NO = 'ADM-0001';

    /**
     * Create the first admin with a random password shown once in the console.
     * Does nothing if the admin already exists, so its password is never reset.
     */
    public function run(): void
    {
        if (User::withTrashed()->where('employee_no', self::EMPLOYEE_NO)->exists()) {
            $this->command->info('Admin '.self::EMPLOYEE_NO.' already exists; skipped.');

            return;
        }

        $password = self::generatePassword();

        $admin = new User;
        $admin->forceFill([
            'employee_no' => self::EMPLOYEE_NO,
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'username' => 'admin',
            'email' => null,
            'password' => $password,
            'must_change_password' => true,
            'status' => User::STATUS_ACTIVE,
        ])->save();

        $admin->assignRole('admin');

        $this->command->warn("Admin created. Username: admin  Password: {$password}");
        $this->command->warn('This password is shown only once. Change it at first login.');
    }

    /**
     * A 16-character password with upper and lower case letters, numbers and
     * easy-to-type symbols (no quotes, backslashes or $ that break when copied).
     */
    private static function generatePassword(): string
    {
        $sets = [
            'abcdefghijkmnpqrstuvwxyz',
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            '23456789',
            '!@#%&*-_=+?',
        ];

        $characters = array_map(
            fn (string $set) => $set[random_int(0, strlen($set) - 1)],
            $sets,
        );

        $all = implode('', $sets);

        while (count($characters) < 16) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        return implode('', (new Randomizer)->shuffleArray($characters));
    }
}
