<?php

namespace Tests\Feature\Materials;

use App\Models\Attendance;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialEntryCostTest extends TestCase
{
    use RefreshDatabase;

    public function testTheMarkupSettingIsSeededAtTwentyPercent(): void
    {
        $this->assertSame('0.20', Settings::get('material_markup_rate'));
        $this->assertNull(Settings::get('missing_key'));
        $this->assertSame('fallback', Settings::get('missing_key', 'fallback'));
    }

    public function testSavingAnEntryFillsSubtotalMarkupAndTotal(): void
    {
        $entry = $this->entry('30.00', '100');

        $entry->refresh();
        $this->assertSame('30.00', $entry->unit_price);
        $this->assertSame('100.00', $entry->quantity);
        $this->assertSame('0.2000', $entry->markup_rate);
        $this->assertSame('3000.00', $entry->subtotal);
        $this->assertSame('600.00', $entry->markup_amount);
        $this->assertSame('3600.00', $entry->total_cost);
    }

    public function testADecimalQuantityIsCostedExactly(): void
    {
        $entry = $this->entry('12.50', '3.5')->refresh();

        $this->assertSame('43.75', $entry->subtotal);
        $this->assertSame('8.75', $entry->markup_amount);
        $this->assertSame('52.50', $entry->total_cost);
    }

    public function testChangingTheQuantityRecomputesTheAmountsWithTheSavedRate(): void
    {
        $entry = $this->entry('30.00', '100');
        $this->setMarkup('0.25');

        $entry->quantity = '50';
        $entry->save();

        $entry->refresh();
        $this->assertSame('0.2000', $entry->markup_rate);
        $this->assertSame('1500.00', $entry->subtotal);
        $this->assertSame('300.00', $entry->markup_amount);
        $this->assertSame('1800.00', $entry->total_cost);
    }

    public function testTheSavedMarkupRateIsKeptWhenTheSettingChanges(): void
    {
        $old = $this->entry('30.00', '100');

        $this->setMarkup('0.25');

        $this->assertSame('0.2000', $old->refresh()->markup_rate);
        $this->assertSame('3600.00', $old->total_cost);

        $new = $this->entry('30.00', '100')->refresh();
        $this->assertSame('0.2500', $new->markup_rate);
        $this->assertSame('750.00', $new->markup_amount);
        $this->assertSame('3750.00', $new->total_cost);
    }

    public function testRunningExpensesAddLaborAndMaterials(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->create(); // ₱360.00 closed
        $this->entry('30.00', '100', $project); // ₱3,600.00

        $this->assertSame('360.00', $project->laborTotal());
        $this->assertSame('3600.00', $project->materialsTotal());
        $this->assertSame('3960.00', $project->runningExpenses());
    }

    public function testRunningExpensesLeaveOutOpenAttendanceAndArchivedEntries(): void
    {
        $project = Project::factory()->ongoing()->create();
        Attendance::factory()->for($project)->create();
        Attendance::factory()->for($project)->open()->create(['labor_cost' => null, 'hours_worked' => null]);
        $this->entry('30.00', '100', $project);
        $this->entry('12.50', '3.5', $project)->delete(); // archived, not counted

        $this->assertSame('3600.00', $project->materialsTotal());
        $this->assertSame('3960.00', $project->runningExpenses());
    }

    public function testAProjectWithoutCostsHasZeroRunningExpenses(): void
    {
        $project = Project::factory()->ongoing()->create();

        $this->assertSame('0.00', $project->materialsTotal());
        $this->assertSame('0.00', $project->runningExpenses());
    }

    public function testEntriesAreArchivedNotDeleted(): void
    {
        $entry = $this->entry('30.00', '100');

        $entry->delete();

        $this->assertSoftDeleted($entry);
        $this->assertSame(1, ProjectMaterialEntry::withTrashed()->count());
    }

    public function testEntryRelations(): void
    {
        $user = User::factory()->staff()->create();
        $project = Project::factory()->ongoing()->create();
        $material = Material::factory()->create();

        $entry = ProjectMaterialEntry::factory()->for($project)->for($material)->create(['entered_by' => $user->id]);

        $this->assertTrue($entry->project->is($project));
        $this->assertTrue($entry->material->is($material));
        $this->assertTrue($entry->enteredBy->is($user));
        $this->assertTrue($project->materialEntries()->first()->is($entry));
    }

    private function entry(string $price, string $quantity, ?Project $project = null): ProjectMaterialEntry
    {
        return ProjectMaterialEntry::factory()
            ->for($project ?? Project::factory()->ongoing())
            ->create(['unit_price' => $price, 'quantity' => $quantity]);
    }

    private function setMarkup(string $rate): void
    {
        Settings::set('material_markup_rate', $rate);
    }
}
