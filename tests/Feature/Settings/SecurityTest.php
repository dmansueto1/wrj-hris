<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function testSecuritySettingsPageCanBeRendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();
    }

    public function testSecuritySettingsPageRequiresPasswordConfirmationWhenEnabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function testSecuritySettingsPageRendersWithoutTwoFactorWhenFeatureIsDisabled(): void
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Update password')
            ->assertDontSee('Manage your passkeys for passwordless sign-in')
            ->assertDontSee('Add a passkey to sign in without a password')
            ->assertDontSee('Two-factor authentication');
    }

    public function testPasswordCanBeUpdated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function testCorrectPasswordMustBeProvidedToUpdatePassword(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.security')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }
}
