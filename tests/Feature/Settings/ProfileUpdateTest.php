<?php

namespace Tests\Feature\Settings;

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

        Livewire::test('pages::settings.profile')
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

        Livewire::test('pages::settings.profile')
            ->set('email', '')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->email);
    }

    public function testTheProfileFormDoesNotChangeTheName(): void
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
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

        Livewire::test('pages::settings.profile')
            ->set('email', $user->email)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function testUserCanDeleteTheirAccount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::settings.delete-user-modal')
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

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
