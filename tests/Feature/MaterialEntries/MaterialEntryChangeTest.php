<?php

namespace Tests\Feature\MaterialEntries;

use App\Livewire\MaterialEntries\Edit;
use App\Livewire\MaterialEntries\Index;
use App\Models\ProjectMaterialEntry;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialEntryChangeTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-19 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed mid-morning time, so "made today" never flips at midnight during a run.
        $this->travelTo(Carbon::parse(self::NOW, 'Asia/Manila'));
    }

    public function testEditingAnEntryWhoseMaterialAndProjectWereArchivedStillSaves(): void
    {
        $admin = User::factory()->admin()->create();
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $admin->id]);
        $entry->material->delete();
        $entry->project->delete();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['materialEntry' => $entry])
            ->assertSee($entry->material->name)
            ->assertSee($entry->project->name)
            ->set('remarks', 'Receipt found')
            ->call('save')
            ->assertHasNoErrors();

        $entry->refresh();
        $this->assertSame('Receipt found', $entry->remarks);
        $this->assertSame('3600.00', $entry->total_cost);
    }

    public function testStaffCanEditTheirOwnEntryMadeToday(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $staff->id]);
        $this->actingAs($staff);

        $this->get(route('material-entries.edit', $entry))->assertOk();

        Livewire::test(Edit::class, ['materialEntry' => $entry])
            ->set('quantity', '50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('material-entries.index'));

        $entry->refresh();
        $this->assertSame('50.00', $entry->quantity);
        $this->assertSame('1800.00', $entry->total_cost);
        $this->assertSame($staff->id, $entry->entered_by);
    }

    public function testStaffCannotEditAnotherUsersEntry(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = ProjectMaterialEntry::factory()->create();
        $this->actingAs($staff);

        $this->get(route('material-entries.edit', $entry))->assertForbidden();
        Livewire::test(Edit::class, ['materialEntry' => $entry])->assertForbidden();
        Livewire::test(Index::class)->call('archive', $entry->id)->assertForbidden();

        $this->assertNotSoftDeleted($entry);
    }

    public function testStaffCannotEditTheirOwnEntryFromYesterday(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = $this->entryMadeYesterday($staff);
        $this->actingAs($staff);

        $this->get(route('material-entries.edit', $entry))->assertForbidden();
        Livewire::test(Index::class)->call('archive', $entry->id)->assertForbidden();

        $this->assertNotSoftDeleted($entry);
    }

    public function testAdminCanEditAnyEntryIncludingOldOnes(): void
    {
        $admin = User::factory()->admin()->create();
        $entry = $this->entryMadeYesterday(User::factory()->staff()->create());
        $this->actingAs($admin);

        $this->get(route('material-entries.edit', $entry))->assertOk();

        Livewire::test(Edit::class, ['materialEntry' => $entry])
            ->set('unit_price', '25.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('3000.00', $entry->refresh()->total_cost);
    }

    public function testAdminCanArchiveAnyEntry(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $entry = $this->entryMadeYesterday(User::factory()->staff()->create());

        Livewire::test(Index::class)->call('archive', $entry->id)->assertHasNoErrors();

        $this->assertSoftDeleted($entry);
    }

    public function testStaffCanArchiveTheirOwnEntryMadeToday(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $staff->id]);
        $this->actingAs($staff);

        Livewire::test(Index::class)->call('archive', $entry->id)->assertHasNoErrors();

        $this->assertSoftDeleted($entry);
    }

    public function testEditingKeepsTheSavedMarkupRate(): void
    {
        $admin = User::factory()->admin()->create();
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $admin->id]);
        $this->actingAs($admin);
        $this->setMarkup('0.25');

        Livewire::test(Edit::class, ['materialEntry' => $entry])
            ->assertSee('Markup (20%)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0.2000', $entry->refresh()->markup_rate);
        $this->assertSame('3600.00', $entry->total_cost);
    }

    public function testEditingAnEntryWhoseProjectIsNowCompletedKeepsThatProject(): void
    {
        $admin = User::factory()->admin()->create();
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $admin->id]);
        $entry->project->forceFill(['status' => 'completed'])->save();
        $this->actingAs($admin);

        Livewire::test(Edit::class, ['materialEntry' => $entry])
            ->set('remarks', 'Late receipt')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Late receipt', $entry->refresh()->remarks);
    }

    private function entryMadeYesterday(User $by): ProjectMaterialEntry
    {
        $this->travelTo(Carbon::parse(self::NOW, 'Asia/Manila')->subDay());
        $entry = ProjectMaterialEntry::factory()->create(['entered_by' => $by->id]);
        $this->travelTo(Carbon::parse(self::NOW, 'Asia/Manila'));

        return $entry;
    }

    private function setMarkup(string $rate): void
    {
        Settings::set('material_markup_rate', $rate);
    }
}
