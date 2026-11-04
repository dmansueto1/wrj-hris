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

    public function testANewUserStartsWithTwoShiftsADayAndSundayOff(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()
            ->assertSee('Two shifts a day')
            ->call('save')
            ->assertHasNoErrors();

        $worker = User::where('employee_no', 'EMP-0500')->sole();
        $this->assertSame(User::SHIFT_TWO, $worker->shift_type);
        $this->assertSame(7, $worker->shifts()->count());

        $monday = $worker->shifts()->where('weekday', 1)->sole();
        $this->assertFalse($monday->is_rest_day);
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], [$monday->first_in, $monday->first_out, $monday->second_in, $monday->second_out]);

        $sunday = $worker->shifts()->where('weekday', 7)->sole();
        $this->assertTrue($sunday->is_rest_day);
        $this->assertNull($sunday->first_in);
    }

    public function testTheAddUserFormHasTheShiftCardStartingWithTwoShifts(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Create::class)
            ->assertSeeHtml('data-test="shift-card"')
            ->assertSee('Shift type')
            ->assertSet('type', User::SHIFT_TWO)
            ->assertSet('days.1.first_in', '08:00')
            ->assertSet('days.1.second_out', '17:00')
            ->assertSet('days.7.is_rest_day', true)
            ->assertSee('Working days: Mon–Sat · Rest day: Sun')
            ->assertDontSeeHtml('data-test="save-shift-button"');
    }

    public function testTheAdminChoosesAndEditsTheShiftWhileCreatingTheUser(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()
            // Untouched defaults switch to the One shift defaults (08:00–17:00).
            ->set('type', User::SHIFT_ONE)
            ->assertSet('days.1.first_out', '17:00')
            ->set('days.1.first_in', '07:00')
            ->set('days.6.is_rest_day', true)
            ->call('save')
            ->assertHasNoErrors();

        $worker = User::where('employee_no', 'EMP-0500')->sole();
        $this->assertSame(User::SHIFT_ONE, $worker->shift_type);

        $monday = $worker->shifts()->where('weekday', 1)->sole();
        $this->assertSame(['07:00', '17:00', null, null], [$monday->first_in, $monday->first_out, $monday->second_in, $monday->second_out]);
        $this->assertTrue($worker->shifts()->where('weekday', 6)->sole()->is_rest_day);
        $this->assertTrue($worker->shifts()->where('weekday', 7)->sole()->is_rest_day);
    }

    public function testAFlexibleNewUserHasNoWeek(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()->set('type', User::SHIFT_FLEXIBLE)->call('save')->assertHasNoErrors();

        $worker = User::where('employee_no', 'EMP-0500')->sole();
        $this->assertSame(User::SHIFT_FLEXIBLE, $worker->shift_type);
        $this->assertSame(0, $worker->shifts()->count());
    }

    public function testAnInvalidShiftShowsItsErrorsWithTheOtherFieldsAndCreatesNoUser(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillForm()
            ->set('first_name', '')
            ->set('days.2.second_out', '')
            ->call('save')
            // Both at once: the details field and the shift time.
            ->assertHasErrors(['first_name', 'days.2.second_out']);

        $this->assertFalse(User::where('employee_no', 'EMP-0500')->exists());
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
