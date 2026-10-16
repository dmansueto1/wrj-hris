<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditUserTest extends TestCase
{
    use RefreshDatabase;

    public function testEditingTheRateCreatesOneHistoryRowAndShowsBothRates(): void
    {
        $admin = User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('hourly_rate', '90.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('90.00', $worker->fresh()->hourly_rate);
        $this->assertSame(2, $worker->rateHistories()->count());
        $this->assertSame($admin->id, $worker->rateHistories()->latest('id')->first()->changed_by);

        Livewire::test(Edit::class, ['user' => $worker->fresh()])
            ->assertSee('₱ 80.00')
            ->assertSee('₱ 90.00')
            ->assertSee('Ada Admin');
    }

    public function testSavingWithoutChangingTheRateAddsNoHistoryRow(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('position', 'Foreman')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Foreman', $worker->fresh()->position);
        $this->assertSame(1, $worker->rateHistories()->count());
    }

    public function testEditingKeepsTheEmployeeNumberUniqueButIgnoresTheUserItself(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['employee_no' => 'EMP-0001']);
        $worker = User::factory()->worker()->create(['employee_no' => 'EMP-0002']);

        Livewire::test(Edit::class, ['user' => $worker])->call('save')->assertHasNoErrors();

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('employee_no', 'EMP-0001')
            ->call('save')
            ->assertHasErrors(['employee_no' => 'unique']);
    }

    public function testChangingTheRoleUpdatesTheUsersRole(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Edit::class, ['user' => $worker])->set('role', 'staff')->call('save')->assertHasNoErrors();

        $this->assertSame(['staff'], $worker->fresh()->getRoleNames()->all());
    }

    public function testAdminCannotDeactivateThemselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['user' => $admin])
            ->call('deactivate')
            ->assertHasErrors('status');

        $this->assertSame(User::STATUS_ACTIVE, $admin->fresh()->status);
    }

    public function testAdminCannotArchiveThemselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['user' => $admin])->call('archive')->assertHasErrors('status');

        $this->assertNotSoftDeleted($admin);
    }

    public function testAdminCannotDemoteThemselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['user' => $admin])
            ->set('role', 'staff')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function testADeactivatedUserCannotLogInAndReactivatingRestoresAccess(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['user' => $worker])->call('deactivate')->assertHasNoErrors();
        $this->assertSame(User::STATUS_INACTIVE, $worker->fresh()->status);

        auth()->logout();
        $this->post(route('login.store'), ['username' => $worker->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);
        $this->assertGuest();

        $this->actingAs($admin);
        Livewire::test(Edit::class, ['user' => $worker->fresh()])->call('reactivate')->assertHasNoErrors();

        auth()->logout();
        $this->post(route('login.store'), ['username' => $worker->username, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($worker);
    }

    public function testResetPasswordSetsATemporaryPasswordTheUserMustChange(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('new_password', 'Reset!Passw0rd9')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $worker->refresh();
        $this->assertTrue(Hash::check('Reset!Passw0rd9', $worker->password));
        $this->assertTrue($worker->must_change_password);
    }

    public function testResetPasswordFollowsThePasswordRules(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('new_password', 'short')
            ->call('resetPassword')
            ->assertHasErrors('new_password');

        $this->assertFalse($worker->fresh()->must_change_password);
    }

    public function testArchiveSoftDeletesTheUser(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Edit::class, ['user' => $worker])
            ->call('archive')
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted($worker);
    }

    public function testStaffCannotOpenTheEditScreen(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $worker = User::factory()->worker()->create();

        Livewire::test(Edit::class, ['user' => $worker])->assertForbidden();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('users.edit', $worker))
            ->assertForbidden();
    }

    public function testTheEditFormShowsTheUsernameAsAReadOnlyField(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        $html = Livewire::test(Edit::class, ['user' => $worker])->html();

        preg_match('/<input[^>]*data-test="username-field"[^>]*>/', $html, $match);
        $this->assertNotEmpty($match, 'The username field should be on the form.');
        $this->assertStringContainsString('value="jdoe"', $match[0]);
        $this->assertStringContainsString('readonly', $match[0]);
    }

    public function testRenamingAUserKeepsTheirUsername(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        Livewire::test(Edit::class, ['user' => $worker])
            ->set('last_name', 'Smith')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('jdoe', $worker->fresh()->username);
        $this->assertSame('John Smith', $worker->fresh()->name);
    }
}
