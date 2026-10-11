<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaselineRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function testRegistrationPageDoesNotExist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function testGuestsVisitingTheHomePageAreRedirectedToLogin(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function testSignedInUsersVisitingTheHomePageAreRedirectedToClock(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect('/clock');
    }
}
