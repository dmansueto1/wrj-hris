<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function testLoginByEmployeeNumberFails(): void
    {
        $user = User::factory()->create(['employee_no' => 'EMP-0042']);

        $this->post(route('login.store'), [
            'username' => 'EMP-0042',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertNotSame('emp-0042', $user->username);
    }

    public function testInactiveUserIsRejectedWithTheInactiveMessage(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'username' => 'Your account is inactive. Please contact the administrator.',
        ]);

        $this->assertGuest();
    }

    public function testInactiveUserWithAWrongPasswordGetsTheNormalFailureMessage(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => __('auth.failed')]);
    }

    public function testTheSixthAttemptAfterFiveFailuresIsLockedEvenWithTheCorrectPassword(): void
    {
        $user = User::factory()->create();

        $this->failLogin($user, 5);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'username' => 'Too many failed login attempts. Please try again in 15 minutes.',
        ]);

        $this->assertGuest();
    }

    public function testTheLockLastsFifteenMinutesFromTheFifthFailure(): void
    {
        $user = User::factory()->create();

        $this->failLogin($user, 4);
        $this->travel(10)->minutes();
        $this->failLogin($user, 1);

        $this->travel(14)->minutes();
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password']);
        $this->assertGuest();

        $this->travel(2)->minutes();
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function testASuccessfulLoginClearsTheFailedAttempts(): void
    {
        $user = User::factory()->create();

        $this->failLogin($user, 4);
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password']);
        $this->post(route('logout'));

        $this->failLogin($user, 4);
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function testTheLockIsPerUsernameSoOtherUsersCanStillLogIn(): void
    {
        $locked = User::factory()->create();
        $other = User::factory()->create();

        $this->failLogin($locked, 5);

        $this->post(route('login.store'), ['username' => $other->username, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($other);
    }

    private function failLogin(User $user, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->post(route('login.store'), [
                'username' => $user->username,
                'password' => 'wrong-password',
            ]);
        }
    }
}
