<?php

namespace Tests\Feature\MaterialEntries;

use App\Livewire\MaterialEntries\Index;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialEntriesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function testAWorkerGets403(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->get('/material-entries')->assertForbidden();
        Livewire::test(Index::class)->assertForbidden();
    }

    public function testStaffAndAdminSeeTheList(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            $this->get('/material-entries')->assertOk()->assertSee('Material Entries');
        }
    }

    public function testTheListShowsEachColumnAndATotalsRow(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $by = User::factory()->staff()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $project = Project::factory()->ongoing()->create(['name' => 'Riverside Tower']);
        $material = Material::factory()->create(['name' => 'Hollow blocks 4"', 'unit' => 'pcs']);
        ProjectMaterialEntry::factory()->create(['project_id' => $project->id, 'material_id' => $material->id, 'entered_by' => $by->id]);
        ProjectMaterialEntry::factory()->create(['project_id' => $project->id, 'unit_price' => '12.50', 'quantity' => '3.5', 'entered_by' => $by->id]);

        Livewire::test(Index::class)
            ->assertSee('Riverside Tower')
            ->assertSee('Hollow blocks 4"')
            ->assertSee('100.00 pcs')
            ->assertSee('₱ 30.00')
            ->assertSee('₱ 3,000.00')
            ->assertSee('₱ 600.00')
            ->assertSee('₱ 3,600.00')
            ->assertSee('Maria Santos')
            ->assertSet('totals.subtotal', '3043.75')
            ->assertSet('totals.markup', '608.75')
            ->assertSet('totals.total', '3652.50')
            ->assertSee('₱ 3,652.50');
    }

    public function testFiltersByProjectMaterialAndDateRange(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $tower = Project::factory()->ongoing()->create(['name' => 'Riverside Tower']);
        $bridge = Project::factory()->ongoing()->create(['name' => 'North Bridge']);
        $cement = Material::factory()->create(['name' => 'Portland cement']);
        $blocks = Material::factory()->create(['name' => 'Hollow blocks']);

        $a = ProjectMaterialEntry::factory()->create(['project_id' => $tower->id, 'material_id' => $cement->id, 'entry_date' => '2026-11-02']);
        $b = ProjectMaterialEntry::factory()->create(['project_id' => $bridge->id, 'material_id' => $blocks->id, 'entry_date' => '2026-11-05']);
        $c = ProjectMaterialEntry::factory()->create(['project_id' => $tower->id, 'material_id' => $blocks->id, 'entry_date' => '2026-11-10']);

        $ids = fn ($component) => $component->instance()->entries->pluck('id')->sort()->values()->all();

        $component = Livewire::test(Index::class);
        $this->assertSame([$a->id, $b->id, $c->id], $ids($component));

        $component->set('project', (string) $tower->id);
        $this->assertSame([$a->id, $c->id], $ids($component));

        $component->set('material', (string) $blocks->id);
        $this->assertSame([$c->id], $ids($component));

        $component->call('clearFilters')->set('from', '2026-11-03')->set('to', '2026-11-06');
        $this->assertSame([$b->id], $ids($component));
        $component->assertSet('totals.total', '3600.00');
    }

    public function testArchivedEntriesAreNotListed(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $entry = ProjectMaterialEntry::factory()->create();
        $entry->delete();

        $this->assertCount(0, Livewire::test(Index::class)->instance()->entries);
    }

    public function testTheListIsPaginatedTenRowsByDefault(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        ProjectMaterialEntry::factory()->count(12)->create();

        $component = Livewire::test(Index::class);
        $this->assertCount(10, $component->instance()->entries);

        $component->set('perPage', 25);
        $this->assertCount(12, $component->instance()->entries);
    }
}
