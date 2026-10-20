<?php

namespace Tests\Feature\Materials;

use App\Livewire\Materials\Create;
use App\Livewire\Materials\Edit;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialFormTest extends TestCase
{
    use RefreshDatabase;

    public function testStaffCreatesAMaterialAndItAppearsInTheList(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);

        $this->fillCreateForm()->call('save')->assertHasNoErrors()->assertRedirect(route('materials.index'));

        $material = Material::sole();
        $this->assertSame('HB-04', $material->code);
        $this->assertSame('Hollow blocks 4"', $material->name);
        $this->assertSame('pcs', $material->unit);
        $this->assertSame('30.00', $material->default_price);
        $this->assertSame(Material::STATUS_ACTIVE, $material->status);

        $this->get(route('materials.index'))
            ->assertOk()
            ->assertSee('HB-04')
            ->assertSee('Hollow blocks 4"')
            ->assertSee('₱ 30.00');
    }

    public function testAdminCreatesAMaterialToo(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillCreateForm()->call('save')->assertHasNoErrors();

        $this->assertSame(1, Material::count());
    }

    public function testADuplicateCodeFailsValidationRegardlessOfCase(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->create(['code' => 'HB-04']);

        $this->fillCreateForm()->set('code', 'hb-04')->call('save')->assertHasErrors(['code' => 'unique']);
    }

    public function testAnArchivedMaterialsCodeCannotBeReused(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->create(['code' => 'HB-04'])->delete();

        $this->fillCreateForm()->call('save')->assertHasErrors(['code' => 'unique']);
    }

    public function testANegativePriceFailsValidation(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillCreateForm()->set('default_price', '-1')->call('save')->assertHasErrors('default_price');
        $this->fillCreateForm()->set('default_price', '30.555')->call('save')->assertHasErrors('default_price');
        $this->fillCreateForm()->set('default_price', '0')->call('save')->assertHasNoErrors();
    }

    public function testTheUnitMustBeOneOfTheOfferedUnits(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillCreateForm()->set('unit', 'boxes')->call('save')->assertHasErrors('unit');
    }

    public function testRequiredFields(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Create::class)
            ->set('unit', '')
            ->call('save')
            ->assertHasErrors(['code' => 'required', 'name' => 'required', 'unit' => 'required', 'default_price' => 'required']);
    }

    public function testStaffEditsAMaterial(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $material = Material::factory()->create(['code' => 'HB-04', 'default_price' => '30.00']);

        Livewire::test(Edit::class, ['material' => $material])
            ->assertSet('code', 'HB-04')
            ->set('default_price', '32.50')
            ->set('unit', 'pcs')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('materials.index'));

        $this->assertSame('32.50', $material->fresh()->default_price);
    }

    public function testEditingKeepsTheCodeUniqueButIgnoresTheMaterialItself(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Material::factory()->create(['code' => 'HB-04']);
        $material = Material::factory()->create(['code' => 'HB-06']);

        Livewire::test(Edit::class, ['material' => $material])->call('save')->assertHasNoErrors();

        Livewire::test(Edit::class, ['material' => $material])
            ->set('code', 'hb-04')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function testWorkersGet403OnCreateAndEdit(): void
    {
        $material = Material::factory()->create();
        $this->actingAs(User::factory()->worker()->create());

        $this->get(route('materials.create'))->assertForbidden();
        $this->get(route('materials.edit', $material))->assertForbidden();
        Livewire::test(Create::class)->assertForbidden();
    }

    private function fillCreateForm(): Testable
    {
        return Livewire::test(Create::class)
            ->set('code', 'hb-04')
            ->set('name', 'Hollow blocks 4"')
            ->set('unit', 'pcs')
            ->set('default_price', '30.00');
    }
}
