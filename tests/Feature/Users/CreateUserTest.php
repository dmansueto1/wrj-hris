<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\Create;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCreatesAWorkerWhoMustChangeTheirPassword(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->fillForm()->call('save')->assertHasNoErrors();

        $worker = User::where('employee_no', 'EMP-0500')->sole();

        $this->assertTrue($worker->hasRole('worker'));
        $this->assertTrue($worker->must_change_password);
        $this->assertSame('jdoe', $worker->username);
        $this->assertSame('John Doe', $worker->name);
        $this->assertSame('80.00', $worker->hourly_rate);
        $this->assertSame(User::STATUS_ACTIVE, $worker->status);
        $this->assertNull($worker->email);
        $this->assertSame($admin->id, $worker->rateHistories()->sole()->changed_by);
    }

    public function testAfterSavingTheAdminSeesTheGeneratedUsername(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = $this->fillForm()->call('save');

        $worker = User::where('employee_no', 'EMP-0500')->sole();
        $component->assertRedirect(route('users.edit', $worker));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('users.edit', $worker))
            ->assertSee('jdoe');
    }

    public function testTheNewWorkerLogsInAndIsForcedToChangeThePassword(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->fillForm()->call('save');
        auth()->logout();

        $this->post(route('login.store'), ['username' => 'jdoe', 'password' => 'Temp!Passw0rd1'])
            ->assertSessionHasNoErrors();

        $this->get(route('clock'))->assertRedirect(route('password.change'));
    }

    public function testADuplicateEmployeeNumberShowsAValidationError(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['employee_no' => 'EMP-0500']);

        $this->fillForm()->call('save')->assertHasErrors(['employee_no' => 'unique']);
    }

    public function testAnArchivedUsersEmployeeNumberCannotBeReused(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['employee_no' => 'EMP-0500'])->delete();

        $this->fillForm()->call('save')->assertHasErrors(['employee_no' => 'unique']);
    }

    public function testADuplicateEmailShowsAValidationError(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['email' => 'taken@example.com']);

        $this->fillForm()->set('email', 'taken@example.com')->call('save')->assertHasErrors(['email' => 'unique']);
    }

    public function testTheHourlyRateMustBeBetweenZeroAnd99999Point99(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()->set('hourly_rate', '100000')->call('save')->assertHasErrors('hourly_rate');
        $this->fillForm()->set('hourly_rate', '-1')->call('save')->assertHasErrors('hourly_rate');
        $this->fillForm()->set('hourly_rate', '80.555')->call('save')->assertHasErrors('hourly_rate');
        $this->fillForm()->set('hourly_rate', '99999.99')->call('save')->assertHasNoErrors();
    }

    public function testTheTemporaryPasswordMustFollowThePasswordRules(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()->set('password', 'password123')->call('save')->assertHasErrors('password');
    }

    public function testTheRoleMustBeAKnownRole(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()->set('role', 'superuser')->call('save')->assertHasErrors('role');
    }

    public function testStaffCannotCreateUsers(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Create::class)->assertForbidden();
    }

    private function fillForm(): Testable
    {
        return Livewire::test(Create::class)
            ->set('employee_no', 'EMP-0500')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', '')
            ->set('role', 'worker')
            ->set('position', 'Carpenter')
            ->set('hourly_rate', '80.00')
            ->set('password', 'Temp!Passw0rd1');
    }

    public function testTheUsernameFieldPreviewsTheGeneratedUsername(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Create::class)
            ->assertSet('usernamePreview', '')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->assertSet('usernamePreview', 'jdoe')
            ->assertSeeHtml('value="jdoe"');
    }

    public function testTheUsernamePreviewSkipsATakenUsername(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        Livewire::test(Create::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->assertSet('usernamePreview', 'jdoe2');
    }

    public function testTheUsernameFieldIsReadOnly(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Create::class)->assertSeeHtml('data-test="username-field"');
        $this->assertStringContainsString('readonly', $this->usernameInputHtml(Livewire::test(Create::class)->html()));
    }

    private function usernameInputHtml(string $html): string
    {
        preg_match('/<input[^>]*data-test="username-field"[^>]*>/', $html, $match);

        return $match[0] ?? '';
    }
}
