<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function testAUserWhoMustChangeTheirPasswordIsRedirectedFromEveryPage(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('profile.edit'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();
    }

    public function testAUserWhoMustChangeTheirPasswordCanStillLogOut(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function testChangingThePasswordClearsTheFlagAndOpensTheApp(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'Str0ng!Passw0rd')
            ->set('password_confirmation', 'Str0ng!Passw0rd')
            ->call('changePassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Str0ng!Passw0rd', $user->password));
        $this->get(route('dashboard'))->assertOk();
    }

    public function testTheNewPasswordMustFollowThePasswordRules(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('changePassword')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function testTheCurrentPasswordMustBeCorrect(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'Str0ng!Passw0rd')
            ->set('password_confirmation', 'Str0ng!Passw0rd')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function testUsersWithoutTheFlagAreNotRedirected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function testAUserWhoMustChangeTheirPasswordCannotRunOtherLivewireActions(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => true]));

        $this->livewireUpdate('settings.profile')->assertForbidden();
    }

    public function testLivewireRequestsForTheChangePasswordPageAreAllowed(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => true]));

        $this->assertNotSame(403, $this->livewireUpdate('auth.change-password')->getStatusCode());
    }

    /**
     * Send a Livewire update request for the named component through the full HTTP middleware stack.
     */
    private function livewireUpdate(string $component): TestResponse
    {
        $snapshot = json_encode(['data' => [], 'memo' => ['id' => 'test', 'name' => $component], 'checksum' => 'x']);

        return $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), [
                'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
            ]);
    }
}
