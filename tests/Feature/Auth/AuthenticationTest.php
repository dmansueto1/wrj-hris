<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function testLoginScreenCanBeRendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Username')
            ->assertDontSee('Email address')
            ->assertSee('images/wrj88-logo.png');
    }

    public function testTheLoginPageLinksWorkersToTheKiosk(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeHtml('data-test="kiosk-link"')
            ->assertSee(route('kiosk'))
            ->assertSee('Clocking in on site?');
    }

    public function testUsersCanAuthenticateWithTheirUsername(): void
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        $response = $this->post(route('login.store'), [
            'username' => 'jdoe',
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clock', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function testAdminWithoutEmailCanAuthenticateWithUsernameAdmin(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin', 'email' => null]);

        $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function testUsernameIsNotCaseSensitive(): void
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        $this->post(route('login.store'), [
            'username' => 'JDoe',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function testUsersCanNotAuthenticateWithTheirEmail(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function testUsersCanNotAuthenticateWithInvalidPassword(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function testTheLoginErrorIsShownAboveTheUsernameField(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $html = $this->get(route('login'))->getContent();
        $error = __('auth.failed');

        $this->assertSame(1, substr_count($html, $error), 'The error should appear exactly once.');
        $this->assertLessThan(strpos($html, 'name="username"'), strpos($html, $error));
    }

    public function testUsersCanLogout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
