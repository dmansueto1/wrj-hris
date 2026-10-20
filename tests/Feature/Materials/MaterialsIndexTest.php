<?php

namespace Tests\Feature\Materials;

use App\Livewire\Materials\Index;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaterialsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function testWorkerGets403OnMaterials(): void
    {
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('materials.index'))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function testAdminAndStaffSeeTheList(string $role): void
    {
        Material::factory()->create(['code' => 'HB-04', 'name' => 'Hollow blocks 4"', 'unit' => 'pcs', 'default_price' => '30.00']);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('materials.index'))
            ->assertOk()
            ->assertSee('HB-04')
            ->assertSee('Hollow blocks 4"')
            ->assertSee('pcs')
            ->assertSee('₱ 30.00')
            ->assertSee('Active')
            ->assertSeeHtml('data-test="add-material-button"');
    }

    public function testSearchByCodeOrName(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->create(['code' => 'HB-04', 'name' => 'Hollow blocks 4"']);
        Material::factory()->create(['code' => 'CEM-40', 'name' => 'Portland cement 40kg']);

        Livewire::test(Index::class)
            ->set('search', 'hollow')
            ->assertSee('HB-04')
            ->assertDontSee('CEM-40')
            ->set('search', 'cem-40')
            ->assertSee('CEM-40')
            ->assertDontSee('HB-04');
    }

    public function testFilterByStatusAndClearFilters(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->create(['code' => 'HB-04']);
        Material::factory()->inactive()->create(['code' => 'OLD-01']);

        Livewire::test(Index::class)
            ->set('status', Material::STATUS_INACTIVE)
            ->assertSee('OLD-01')
            ->assertDontSee('HB-04')
            ->assertSee('Status: Inactive')
            ->call('clearFilters')
            ->assertSet('status', '')
            ->assertSee('HB-04');
    }

    public function testTheStatCardsCountMaterials(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->count(2)->create();
        Material::factory()->inactive()->create();
        Material::factory()->create()->delete();

        Livewire::test(Index::class)->assertSet('summary', ['total' => 3, 'active' => 2, 'inactive' => 1]);
    }

    public function testDeactivateReactivateAndArchiveFromTheList(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $material = Material::factory()->create(['code' => 'HB-04']);

        Livewire::test(Index::class)->call('deactivate', $material->id);
        $this->assertSame(Material::STATUS_INACTIVE, $material->fresh()->status);

        Livewire::test(Index::class)->call('reactivate', $material->id);
        $this->assertSame(Material::STATUS_ACTIVE, $material->fresh()->status);

        Livewire::test(Index::class)->call('archive', $material->id)->assertDontSee('HB-04');
        $this->assertSoftDeleted($material);
    }

    public function testActionsAreRefusedWhenThePermissionIsLost(): void
    {
        $material = Material::factory()->create();
        $this->actingAs(User::factory()->staff()->create());
        $list = Livewire::test(Index::class);

        $this->actingAs(User::factory()->worker()->create());
        $list->call('archive', $material->id)->assertForbidden();

        $this->assertNotSoftDeleted($material);
    }

    public function testTheListIsPaginatedTenRowsByDefault(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        foreach (range(1, 11) as $i) {
            Material::factory()->create(['code' => sprintf('MAT-%03d', $i)]);
        }

        Livewire::test(Index::class)
            ->assertSee('MAT-010')
            ->assertDontSee('MAT-011')
            ->assertSee('Rows per page');
    }

    public function testAnEmptyListShowsTheEmptyState(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Index::class)->assertSee('No materials yet');
    }
}
