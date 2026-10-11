<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('each role has exactly the permissions from the PRD', function (string $role, array $permissions) {
    $actual = Role::findByName($role)->permissions->pluck('name')->sort()->values()->all();

    expect($actual)->toBe(collect($permissions)->sort()->values()->all());
})->with([
    'admin' => ['admin', [
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
    ]],
    'staff' => ['staff', [
        'attendance.clock',
        'attendance.view-own',
        'attendance.view-all',
        'materials.manage',
        'material-entries.create',
        'expenses.view',
        'reports.export',
    ]],
    'worker' => ['worker', [
        'attendance.clock',
        'attendance.view-own',
    ]],
]);

test('running the seeder twice does not create duplicates', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(10)
        ->and(DB::table('role_has_permissions')->count())->toBe(19);
});

test('database seeder creates the three roles', function () {
    $this->seed();

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'staff', 'worker']);
});

test('permission middleware blocks roles without the permission', function (string $role, int $status) {
    Route::middleware(['web', 'auth', 'permission:users.manage'])
        ->get('/_test/users-manage', fn () => 'ok');

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/_test/users-manage')->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'staff' => ['staff', 403],
    'worker' => ['worker', 403],
]);
