<?php

namespace Tests\Feature\MaterialEntries;

use App\Livewire\MaterialEntries\Create;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaterialEntryFormTest extends TestCase
{
    use RefreshDatabase;

    public function testThePreviewShowsSubtotalMarkupAndTotalForOneHundredHollowBlocks(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillForm()
            ->assertSet('preview.subtotal', '3000.00')
            ->assertSet('preview.markup', '600.00')
            ->assertSet('preview.total', '3600.00')
            ->assertSee('₱ 3,000.00')
            ->assertSee('₱ 600.00')
            ->assertSee('₱ 3,600.00')
            ->assertSee('Markup (20%)');
    }

    public function testThePreviewIsEmptyUntilQuantityAndPriceAreValid(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Create::class)
            ->set('quantity', 'abc')
            ->set('unit_price', '30')
            ->assertSet('preview', null);
    }

    public function testSelectingAMaterialFillsTheUnitPriceWhichStaysEditable(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $material = Material::factory()->create(['default_price' => '30.00']);

        Livewire::test(Create::class)
            ->set('material_id', (string) $material->id)
            ->assertSet('unit_price', '30.00')
            ->set('unit_price', '28.50')
            ->assertSet('unit_price', '28.50');
    }

    public function testSavingCreatesAnEntryEnteredByTheCurrentUser(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);

        $this->fillForm()
            ->set('remarks', 'Delivered to site')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('material-entries.index'));

        $entry = ProjectMaterialEntry::sole();
        $this->assertSame($staff->id, $entry->entered_by);
        $this->assertSame(today()->toDateString(), $entry->entry_date->toDateString());
        $this->assertSame('100.00', $entry->quantity);
        $this->assertSame('30.00', $entry->unit_price);
        $this->assertSame('0.2000', $entry->markup_rate);
        $this->assertSame('3000.00', $entry->subtotal);
        $this->assertSame('600.00', $entry->markup_amount);
        $this->assertSame('3600.00', $entry->total_cost);
        $this->assertSame('Delivered to site', $entry->remarks);
    }

    public function testTheEntryDateDefaultsToToday(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Create::class)->assertSet('entry_date', today()->toDateString());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'quantity 0' => ['quantity', '0', 'quantity'],
            'negative quantity' => ['quantity', '-1', 'quantity'],
            'negative unit price' => ['unit_price', '-5', 'unit_price'],
            'future date' => ['entry_date', '2099-01-01', 'entry_date'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesFailValidation(string $field, string $value, string $errorField): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillForm()->set($field, $value)->call('save')->assertHasErrors($errorField);

        $this->assertSame(0, ProjectMaterialEntry::count());
    }

    public function testTomorrowsDateFailsValidation(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillForm()->set('entry_date', today()->addDay()->toDateString())->call('save')->assertHasErrors('entry_date');

        $this->assertSame(0, ProjectMaterialEntry::count());
    }

    public function testAZeroUnitPriceIsAllowed(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->fillForm()->set('unit_price', '0')->call('save')->assertHasNoErrors();

        $this->assertSame('0.00', ProjectMaterialEntry::sole()->total_cost);
    }

    public function testAnInactiveMaterialIdSubmittedDirectlyFailsValidation(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $inactive = Material::factory()->inactive()->create();

        $this->fillForm()->set('material_id', (string) $inactive->id)->call('save')->assertHasErrors('material_id');

        $this->assertSame(0, ProjectMaterialEntry::count());
    }

    public function testAnArchivedMaterialIdSubmittedDirectlyFailsValidation(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $archived = Material::factory()->create();
        $archived->delete();

        $this->fillForm()->set('material_id', (string) $archived->id)->call('save')->assertHasErrors('material_id');
    }

    public function testACompletedOrPlanningProjectIdSubmittedDirectlyFailsValidation(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        foreach ([Project::factory()->completed()->create(), Project::factory()->planning()->create()] as $project) {
            $this->fillForm()->set('project_id', (string) $project->id)->call('save')->assertHasErrors('project_id');
        }

        $this->assertSame(0, ProjectMaterialEntry::count());
    }

    public function testAnOnHoldProjectCanBeChosen(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $onHold = Project::factory()->onHold()->create();

        $this->fillForm()->set('project_id', (string) $onHold->id)->call('save')->assertHasNoErrors();

        $this->assertSame($onHold->id, ProjectMaterialEntry::sole()->project_id);
    }

    public function testOnlyOngoingAndOnHoldProjectsAndActiveMaterialsAreOffered(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Project::factory()->ongoing()->create(['name' => 'Ongoing Tower']);
        Project::factory()->onHold()->create(['name' => 'Paused Bridge']);
        Project::factory()->completed()->create(['name' => 'Finished Mall']);
        Material::factory()->create(['name' => 'Active Cement']);
        Material::factory()->inactive()->create(['name' => 'Old Gravel']);

        Livewire::test(Create::class)
            ->assertSee('Ongoing Tower')
            ->assertSee('Paused Bridge')
            ->assertDontSee('Finished Mall')
            ->assertSee('Active Cement')
            ->assertDontSee('Old Gravel');
    }

    public function testAWorkerCannotOpenTheForm(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $this->get(route('material-entries.create'))->assertForbidden();
        Livewire::test(Create::class)->assertForbidden();
        $this->assertSame(0, ProjectMaterialEntry::count());
    }

    public function testStaffCanOpenTheForm(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->get(route('material-entries.create'))->assertOk()->assertSee('Record material purchase');
    }

    private function fillForm(): Testable
    {
        $project = Project::factory()->ongoing()->create();
        $material = Material::factory()->create(['name' => 'Hollow blocks 4"', 'unit' => 'pcs', 'default_price' => '30.00']);

        return Livewire::test(Create::class)
            ->set('project_id', (string) $project->id)
            ->set('material_id', (string) $material->id)
            ->set('quantity', '100');
    }
}
