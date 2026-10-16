<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\DeleteUserModal;
use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function testProfilePageIsDisplayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function testProfileInformationCanBeUpdated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function testProfilePageWorksForAUserWithoutAnEmail(): void
    {
        $user = User::factory()->create(['email' => null]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        Livewire::test(Profile::class)
            ->set('email', '')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->email);
    }

    public function testTheProfileFormDoesNotChangeTheName(): void
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('email', 'john@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('John Doe', $user->fresh()->name);
        $this->assertSame('John', $user->fresh()->first_name);
    }

    public function testEmailVerificationStatusIsUnchangedWhenEmailAddressIsUnchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('email', $user->email)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function testUserCanDeleteTheirAccount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DeleteUserModal::class)
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertSoftDeleted($user);
        $this->assertGuest();
    }

    public function testCorrectPasswordMustBeProvidedToDeleteAccount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DeleteUserModal::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function testTheProfilePageShowsTheUsersOwnUsernameReadOnly(): void
    {
        $this->actingAs(User::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']));

        $html = $this->get(route('profile.edit'))->assertOk()->getContent();

        preg_match('/<input[^>]*data-test="username-field"[^>]*>/', $html, $match);
        $this->assertNotEmpty($match, 'The username field should be on the profile page.');
        $this->assertStringContainsString('value="msantos"', $match[0]);
        $this->assertStringContainsString('readonly', $match[0]);
    }
}
