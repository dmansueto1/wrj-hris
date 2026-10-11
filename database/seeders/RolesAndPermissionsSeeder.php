<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permissions granted to each role (PRD section 3).
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'attendance.clock',
            'attendance.view-own',
            'attendance.view-all',
            'attendance.correct',
            'users.manage',
            'projects.manage',
            'materials.manage',
            'material-entries.create',
            'expenses.view',
            'reports.export',
        ],
        'staff' => [
            'attendance.clock',
            'attendance.view-own',
            'attendance.view-all',
            'materials.manage',
            'material-entries.create',
            'expenses.view',
            'reports.export',
        ],
        'worker' => [
            'attendance.clock',
            'attendance.view-own',
        ],
    ];

    /**
     * Create the roles and permissions. Safe to run more than once.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (collect(self::ROLE_PERMISSIONS)->flatten()->unique() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }
    }
}
