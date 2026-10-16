<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Expected status per page for admin, staff and worker (PRD section 3).
     *
     * @return array<string, array{string, int, int, int}>
     */
    public static function pages(): array
    {
        return [
            'Clock In/Out' => ['/clock', 200, 200, 200],
            'My Attendance' => ['/attendance/mine', 200, 200, 200],
            'All Attendance' => ['/attendance', 200, 200, 403],
            'Projects' => ['/projects', 200, 200, 403],
            'Material Entries' => ['/material-entries', 200, 200, 403],
            'Materials' => ['/materials', 200, 200, 403],
            'Users' => ['/users', 200, 403, 403],
            'Reports' => ['/reports', 200, 200, 403],
        ];
    }

    #[DataProvider('pages')]
    public function testEachRoleCanOnlyOpenItsAllowedPages(string $url, int $admin, int $staff, int $worker): void
    {
        foreach (['admin' => $admin, 'staff' => $staff, 'worker' => $worker] as $role => $status) {
            // A recent password confirmation, so password.confirm (on /users) does not redirect.
            $this->actingAs(User::factory()->{$role}()->create())
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get($url)
                ->assertStatus($status);
        }
    }

    public function testAWorkerTypingTheUsersUrlGets403AndAdminGets200(): void
    {
        $this->actingAs(User::factory()->worker()->create())->get('/users')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/users')
            ->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function urls(): array
    {
        return array_map(fn (array $page) => [$page[0]], self::pages());
    }

    #[DataProvider('urls')]
    public function testGuestsAreSentToLogin(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }
}
