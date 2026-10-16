<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'staff' => ['staff'],
            'worker' => ['worker'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function testStaffAndWorkersGet403OnUsers(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function testAdminMustConfirmTheirPasswordFirst(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index'))
            ->assertRedirect(route('password.confirm'));
    }

    public function testAdminSeesTheUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'employee_no' => 'EMP-0100',
            'position' => 'Mason',
            'hourly_rate' => '85.50',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('EMP-0100')
            ->assertSee('Juan Dela Cruz')
            ->assertSee($worker->username)
            ->assertSee('Worker')
            ->assertSee('Mason')
            ->assertSee('₱ 85.50')
            ->assertSee('Active');
    }

    public function testSearchMatchesNameUsernameOrEmployeeNumber(): void
    {
        $this->actingAs(User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Admin']));
        User::factory()->worker()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'employee_no' => 'EMP-0100']);
        User::factory()->worker()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'employee_no' => 'EMP-0200']);

        Livewire::test(Index::class)
            ->set('search', 'Dela')
            ->assertSee('EMP-0100')
            ->assertDontSee('EMP-0200')
            ->set('search', 'msantos')
            ->assertSee('EMP-0200')
            ->assertDontSee('EMP-0100')
            ->set('search', 'EMP-0100')
            ->assertSee('EMP-0100')
            ->assertDontSee('EMP-0200');
    }

    public function testFilterByRoleAndStatus(): void
    {
        $this->actingAs(User::factory()->admin()->create(['employee_no' => 'ADM-0001']));
        User::factory()->worker()->create(['employee_no' => 'EMP-0100']);
        User::factory()->staff()->create(['employee_no' => 'STF-0100']);
        User::factory()->worker()->inactive()->create(['employee_no' => 'EMP-0200']);

        Livewire::test(Index::class)
            ->set('role', 'worker')
            ->assertSee('EMP-0100')
            ->assertSee('EMP-0200')
            ->assertDontSee('STF-0100')
            ->set('status', User::STATUS_INACTIVE)
            ->assertSee('EMP-0200')
            ->assertDontSee('EMP-0100');
    }

    public function testArchivedUsersAreNotListed(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->worker()->create(['employee_no' => 'EMP-0300'])->delete();

        Livewire::test(Index::class)->assertDontSee('EMP-0300');
    }

    public function testStaffCannotUseTheComponentDirectly(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Index::class)->assertForbidden();
    }
}
