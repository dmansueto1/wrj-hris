<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function officeRoles(): array
    {
        return [
            'admin' => ['admin'],
            'staff' => ['staff'],
        ];
    }

    #[DataProvider('officeRoles')]
    public function testAdminAndStaffAreLoggedOutAfterThirtyOneMinutesIdle(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get(route('clock'))->assertOk();

        $this->travel(31)->minutes();

        $this->get(route('clock'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function testAdminStaysLoggedInWhileActive(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('clock'))->assertOk();

        $this->travel(20)->minutes();
        $this->get(route('clock'))->assertOk();

        $this->travel(20)->minutes();
        $this->get(route('clock'))->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    public function testAWorkerIsNotLoggedOutAfterThirtyOneMinutesIdle(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)->get(route('clock'))->assertOk();

        $this->travel(31)->minutes();

        $this->get(route('clock'))->assertOk();
        $this->assertAuthenticatedAs($worker);
    }

    public function testAdminRestoredFromTheRememberCookieMustLogInAgain(): void
    {
        $admin = User::factory()->admin()->create();

        $this->comeBackLaterWithTheRememberCookie($admin)
            ->get(route('clock'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function testAWorkerRestoredFromTheRememberCookieStaysLoggedIn(): void
    {
        $worker = User::factory()->worker()->create();

        $this->comeBackLaterWithTheRememberCookie($worker)
            ->get(route('clock'))
            ->assertOk();

        $this->assertAuthenticatedAs($worker);
    }

    public function testATimedOutLivewireRequestGetsPageExpired(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('clock'))->assertOk();

        $this->travel(31)->minutes();

        $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(route('default-livewire.update'), ['components' => []])
            ->assertStatus(419);

        $this->assertGuest();
    }

    /**
     * Log in with "Remember me", then simulate the session expiring hours later
     * so only the remember cookie is left.
     */
    private function comeBackLaterWithTheRememberCookie(User $user): static
    {
        $login = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $recaller = collect($login->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'Logging in with "Remember me" should set a remember cookie.');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->travel(3)->hours();

        // The response cookie is already encrypted, so send it as-is.
        return $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue());
    }
}
