<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeSearchSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 15:00:00');
        $this->actingAs(User::factory()->admin()->create(['first_name' => 'Zed', 'last_name' => 'Zamora']));
    }

    public function testTheDailyEmployeeFilterIsASearchableDropdownStartingAtAllEmployees(): void
    {
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Abad', 'employee_no' => 'EMP-0101']);

        $component = Livewire::test(Index::class)
            ->assertSeeHtml('data-test="filter-user"')
            ->assertSeeHtml('searchableSelect(')
            ->assertSeeHtml('placeholder="Search name or employee no."')
            ->assertDontSeeHtml('wire:model.live="user"')
            ->assertSee('All employees');

        $this->assertContains(
            ['value' => (string) $ana->id, 'label' => 'Abad, Ana', 'hint' => 'EMP-0101'],
            $component->instance()->employeeOptions,
        );
    }

    public function testPickingAnEmployeeFiltersTheDailyListAndShowsTheirName(): void
    {
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Abad']);
        User::factory()->worker()->create(['first_name' => 'Ben', 'last_name' => 'Bautista']);

        $component = Livewire::test(Index::class)->set('user', (string) $ana->id);

        $this->assertSame(['Abad'], collect($component->instance()->daily->items())->map(fn (array $row) => $row['user']->last_name)->all());
        $component->assertSee('Employee: Abad, Ana');
    }

    public function testTheByEmployeeFilterIsASearchableDropdownWithAPlaceholder(): void
    {
        $ana = User::factory()->worker()->create(['first_name' => 'Ana', 'last_name' => 'Abad']);

        Livewire::test(Index::class)
            ->set('tab', 'employee')
            ->assertSeeHtml('data-test="filter-employee"')
            ->assertSeeHtml('searchableSelect(')
            ->assertSee('Select employee')
            ->set('employee', (string) $ana->id)
            ->assertSee('Abad, Ana')
            ->assertDontSeeHtml('data-test="choose-employee"');
    }

    public function testArchivedEmployeesCanStillBeFound(): void
    {
        $gone = User::factory()->worker()->create(['first_name' => 'Gina', 'last_name' => 'Gomez']);
        $gone->delete();

        $labels = collect(Livewire::test(Index::class)->instance()->employeeOptions)->pluck('label');

        $this->assertContains('Gomez, Gina', $labels);
    }
}
