<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestsAreRedirectedToTheLoginPage(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function testAuthenticatedUsersCanVisitTheDashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk();
    }
}
