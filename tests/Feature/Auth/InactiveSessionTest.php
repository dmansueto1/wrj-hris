<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function testALoggedInUserIsLoggedOutOnTheNextRequestAfterBeingDeactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)->get(route('clock'))->assertOk();

        app(UserService::class)->deactivate($worker, $admin);

        $this->get(route('clock'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);
        $this->assertGuest();
    }

    public function testADeactivatedUserCannotComeBackWithTheRememberCookie(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create();

        $login = $this->post(route('login.store'), [
            'username' => $worker->username,
            'password' => 'password',
            'remember' => 'on',
        ]);
        $recaller = collect($login->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'Logging in with "Remember me" should set a remember cookie.');

        app(UserService::class)->deactivate($worker, $admin);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // The response cookie is already encrypted, so send it as-is.
        $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->get(route('clock'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function testADeactivatedUsersLivewireRequestGetsPageExpired(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)->get(route('clock'))->assertOk();

        app(UserService::class)->deactivate($worker, $admin);

        $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), ['components' => []])
            ->assertStatus(419);
        $this->assertGuest();
    }

    public function testActiveUsersAreNotAffected(): void
    {
        $this->actingAs(User::factory()->worker()->create())->get(route('clock'))->assertOk();
    }
}
