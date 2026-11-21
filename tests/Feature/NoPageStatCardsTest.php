<?php

namespace Tests\Feature;

use App\Livewire\Attendance;
use App\Livewire\Devices;
use App\Livewire\MaterialEntries;
use App\Livewire\Materials;
use App\Livewire\Projects;
use App\Livewire\Users;
use App\Models\Material;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-15.1: stat cards only on the Dashboard. The menu pages keep their description, filters and
 * tables.
 */
class NoPageStatCardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, array<string, mixed>, string}>
     */
    public static function pages(): array
    {
        return [
            'All Attendance, Daily attendance' => [Attendance\Index::class, [], 'Who is present or absent each day'],
            'All Attendance, By employee' => [Attendance\Index::class, ['tab' => 'employee'], 'Who is present or absent each day'],
            'My Attendance' => [Attendance\Mine::class, [], 'Your attendance by day'],
            'Projects' => [Projects\Index::class, [], 'Projects'],
            'Materials' => [Materials\Index::class, [], 'Materials'],
            'Users' => [Users\Index::class, [], 'Users'],
            'Devices' => [Devices\Index::class, [], 'Devices'],
            'Material Entries' => [MaterialEntries\Index::class, [], 'Material Entries'],
        ];
    }

    /**
     * @param  class-string  $component
     * @param  array<string, mixed>  $set
     */
    #[DataProvider('pages')]
    public function testThePageHasNoStatCards(string $component, array $set, string $text): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $test = Livewire::test($component);
        foreach ($set as $property => $value) {
            $test->set($property, $value);
        }

        $test->assertSee($text)->assertDontSeeHtml('data-stat-card');
    }

    /**
     * @return array<string, array{class-string, array<string, mixed>}>
     */
    public static function filters(): array
    {
        return [
            'Daily attendance' => [Attendance\Index::class, ['date' => '2026-10-20']],
            'By employee' => [Attendance\Index::class, ['tab' => 'employee', 'from' => '2026-10-01']],
            'My Attendance' => [Attendance\Mine::class, ['from' => '2026-10-01']],
            'Projects' => [Projects\Index::class, ['search' => 'river']],
            'Materials' => [Materials\Index::class, ['search' => 'cement']],
            'Users' => [Users\Index::class, ['search' => 'juan']],
            'Devices' => [Devices\Index::class, ['search' => 'tablet']],
            'Material Entries' => [MaterialEntries\Index::class, ['from' => '2026-10-01']],
        ];
    }

    /**
     * Each page's filter still reaches the component and shows its chip, with no cards.
     *
     * @param  class-string  $component
     * @param  array<string, mixed>  $set
     */
    #[DataProvider('filters')]
    public function testEveryPageStillShowsItsActiveFilter(string $component, array $set): void
    {
        $this->travelTo('2026-10-21 15:00:00');
        $this->actingAs(User::factory()->admin()->create());

        $test = Livewire::test($component)->assertDontSeeHtml('data-test="active-filters"');
        foreach ($set as $property => $value) {
            $test->set($property, $value);
        }

        $test->assertSeeHtml('data-test="active-filters"')->assertDontSeeHtml('data-stat-card');
    }

    public function testFiltersStillWorkWithoutTheCards(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Material::factory()->create(['name' => 'Portland cement']);
        Material::factory()->create(['name' => 'Washed sand']);
        Project::factory()->ongoing()->create(['name' => 'Riverside Townhomes']);

        Livewire::test(Materials\Index::class)
            ->set('search', 'cement')
            ->assertSee('Portland cement')
            ->assertDontSee('Washed sand')
            ->assertSeeHtml('data-test="active-filters"');
    }

    public function testTheDashboardUsesStatCards(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee('data-stat-card', false);
    }
}
