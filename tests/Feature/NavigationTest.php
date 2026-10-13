<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ITEMS = [
        'clock',
        'attendance.mine',
        'attendance.index',
        'projects.index',
        'material-entries.index',
        'materials.index',
        'users.index',
        'reports.index',
    ];

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function menuByRole(): array
    {
        return [
            'admin sees everything' => ['admin', self::ALL_ITEMS],
            'staff see everything except Users' => ['staff', [
                'clock',
                'attendance.mine',
                'attendance.index',
                'projects.index',
                'material-entries.index',
                'materials.index',
                'reports.index',
            ]],
            'worker sees only Clock and My Attendance' => ['worker', [
                'clock',
                'attendance.mine',
            ]],
        ];
    }

    /**
     * @param  list<string>  $visible
     */
    #[DataProvider('menuByRole')]
    public function testTheMenuShowsOnlyTheItemsTheRoleMayUse(string $role, array $visible): void
    {
        $this->actingAs(User::factory()->{$role}()->create());

        $response = $this->get(route('clock'))->assertOk();

        foreach (self::ALL_ITEMS as $item) {
            in_array($item, $visible, true)
                ? $response->assertSee('data-test="nav-'.$item.'"', false)
                : $response->assertDontSee('data-test="nav-'.$item.'"', false);
        }
    }

    public function testTheStarterKitDashboardIsGone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('clock'))->assertDontSee('Dashboard');
        $this->get('/dashboard')->assertNotFound();
    }

    public function testTheUserMenuShowsNameEmployeeNumberAndRole(): void
    {
        $this->actingAs(User::factory()->staff()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'employee_no' => 'EMP-0007',
        ]));

        $this->get(route('clock'))
            ->assertSee('Maria Santos')
            ->assertSee('EMP-0007')
            ->assertSee('Staff');
    }
}
