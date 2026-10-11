<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function rolePermissions(): array
    {
        return [
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
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('rolePermissions')]
    public function testEachRoleHasExactlyThePermissionsFromThePrd(string $role, array $permissions): void
    {
        $actual = Role::findByName($role)->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame(collect($permissions)->sort()->values()->all(), $actual);
    }

    public function testRunningTheSeederTwiceDoesNotCreateDuplicates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(3, Role::count());
        $this->assertSame(10, Permission::count());
        $this->assertSame(19, DB::table('role_has_permissions')->count());
    }

    public function testDatabaseSeederCreatesTheThreeRoles(): void
    {
        $this->seed();

        $this->assertSame(['admin', 'staff', 'worker'], Role::pluck('name')->sort()->values()->all());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function usersManageAccess(): array
    {
        return [
            'admin' => ['admin', 200],
            'staff' => ['staff', 403],
            'worker' => ['worker', 403],
        ];
    }

    #[DataProvider('usersManageAccess')]
    public function testPermissionMiddlewareBlocksRolesWithoutThePermission(string $role, int $status): void
    {
        Route::middleware(['web', 'auth', 'permission:users.manage'])
            ->get('/_test/users-manage', fn () => 'ok');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/_test/users-manage')->assertStatus($status);
    }
}
