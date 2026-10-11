<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function testTheAdminSeederCreatesOneAdminWithUsernameAdmin(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('employee_no', 'ADM-0001')->sole();

        $this->assertSame('admin', $admin->username);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->must_change_password);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);
        $this->assertNull($admin->email);
    }

    public function testRunningTheAdminSeederTwiceKeepsOneAdminAndItsPassword(): void
    {
        $this->seed(AdminUserSeeder::class);
        $passwordHash = User::where('employee_no', 'ADM-0001')->value('password');

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('employee_no', 'ADM-0001')->count());
        $this->assertSame($passwordHash, User::where('employee_no', 'ADM-0001')->value('password'));
    }

    public function testTheFullDatabaseSeedCreatesTheRolesAndOneAdmin(): void
    {
        $this->seed();

        $this->assertSame(1, User::count());
        $this->assertTrue(User::sole()->hasRole('admin'));
    }
}
