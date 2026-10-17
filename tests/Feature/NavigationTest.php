<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
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

    public function testTheMenuIsGroupedUnderSectionLabels(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('clock'))
            ->assertSeeInOrder(['Time', 'Clock In/Out', 'All Attendance', 'Operations', 'Projects', 'Materials', 'Admin', 'Users', 'Reports']);
    }

    public function testAWorkerOnlySeesTheTimeSection(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->assertSame(['Time'], array_keys(Navigation::groupedFor(auth()->user())));
    }

    public function testSubPagesBelongToTheirListsMenuItem(): void
    {
        $this->assertSame('users.index', Navigation::owner('users.edit')['route']);
        $this->assertSame('attendance.mine', Navigation::owner('attendance.mine')['route']);
        $this->assertSame('attendance.index', Navigation::owner('attendance.index')['route']);
        $this->assertNull(Navigation::owner('profile.edit'));
    }

    public function testBreadcrumbsShowSectionMenuPageAndSubPageTitle(): void
    {
        $this->assertSame(
            [
                ['label' => 'Admin', 'url' => null],
                ['label' => 'Users', 'url' => route('users.index')],
                ['label' => 'Edit user', 'url' => null],
            ],
            Navigation::breadcrumbs('users.edit', 'Edit user'),
        );

        $this->assertSame(
            [['label' => 'Time', 'url' => null], ['label' => 'Clock In/Out', 'url' => route('clock')]],
            Navigation::breadcrumbs('clock', 'Clock In/Out'),
        );
    }

    public function testOnlyTheCurrentMenuItemIsHighlighted(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->get(route('attendance.mine'))->getContent();

        // The standalone data-current attribute (not the data-current:* Tailwind classes every item carries).
        preg_match_all('/<a\b(?=[^>]*\sdata-current[\s>=])[^>]*>/', $html, $current);
        $this->assertCount(1, $current[0]);
        $this->assertStringContainsString('data-test="nav-attendance.mine"', $current[0][0]);
    }

    public function testTheTopBarHasTheClockButtonForUsersWhoCanClockIn(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->get(route('attendance.mine'))->assertSeeHtml('data-test="topbar-clock-button"');
    }
}
