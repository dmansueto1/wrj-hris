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

    public function testTheStatCardsCountUsersByStatusAndRole(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->worker()->count(3)->create();
        User::factory()->worker()->inactive()->create();
        User::factory()->staff()->create();
        User::factory()->worker()->create()->delete();

        Livewire::test(Index::class)->assertSet('summary', [
            'total' => 6,
            'active' => 5,
            'inactive' => 1,
            'roles' => ['admin' => 1, 'staff' => 1, 'worker' => 4],
        ]);
    }

    public function testActiveFiltersShowAsChipsAndCanBeCleared(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertDontSeeHtml('data-test="active-filters"')
            ->set('search', 'juan')
            ->set('role', 'worker')
            ->assertSeeHtml('data-test="active-filters"')
            ->assertSee('Search: juan')
            ->assertSee('Role: Worker')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('role', '')
            ->assertSet('status', '')
            ->assertDontSeeHtml('data-test="active-filters"');
    }

    public function testAnEmptySearchShowsAFriendlyEmptyState(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('search', 'nobody-matches-this')
            ->assertSee('No users match your search.')
            ->assertSee('Clear filters');
    }

    public function testAdminDeactivatesAndReactivatesAUserFromTheList(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Index::class)->call('deactivate', $worker->id);
        $this->assertSame(User::STATUS_INACTIVE, $worker->fresh()->status);

        Livewire::test(Index::class)->call('reactivate', $worker->id);
        $this->assertSame(User::STATUS_ACTIVE, $worker->fresh()->status);
    }

    public function testAdminCannotDeactivateThemselvesFromTheList(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertDontSeeHtml('wire:click="deactivate('.$admin->id.')"')
            ->call('deactivate', $admin->id);

        $this->assertSame(User::STATUS_ACTIVE, $admin->fresh()->status);
    }

    public function testStaffCannotDeactivateFromTheList(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs(User::factory()->admin()->create());
        $list = Livewire::test(Index::class);

        // The permission is re-checked on every request, so losing it mid-session blocks the action.
        $this->actingAs(User::factory()->staff()->create());
        $list->call('deactivate', $worker->id)->assertForbidden();

        $this->assertSame(User::STATUS_ACTIVE, $worker->fresh()->status);
    }
}
