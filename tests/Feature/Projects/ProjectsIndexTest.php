<?php

namespace Tests\Feature\Projects;

use App\Livewire\Projects\Index;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function testWorkerGets403OnProjects(): void
    {
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('projects.index'))
            ->assertForbidden();
    }

    public function testAdminSeesTheListWithCreateEditAndArchive(): void
    {
        $project = Project::factory()->ongoing()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee('Riverside Townhomes')
            ->assertSee('Ongoing')
            ->assertSee(route('projects.show', $project))
            ->assertSeeHtml('data-test="add-project-button"')
            ->assertSeeHtml('data-test="edit-project-'.$project->id.'"')
            ->assertSeeHtml('data-test="archive-project-'.$project->id.'"');
    }

    public function testStaffSeeTheListReadOnly(): void
    {
        $project = Project::factory()->ongoing()->create(['code' => 'PRJ-001']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee(route('projects.show', $project))
            ->assertDontSeeHtml('data-test="add-project-button"')
            ->assertDontSeeHtml('data-test="edit-project-'.$project->id.'"')
            ->assertDontSeeHtml('data-test="archive-project-'.$project->id.'"');
    }

    public function testSearchByCodeOrName(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['code' => 'PRJ-001', 'name' => 'Riverside Townhomes']);
        Project::factory()->create(['code' => 'PRJ-002', 'name' => 'Hilltop Warehouse']);

        Livewire::test(Index::class)
            ->set('search', 'river')
            ->assertSee('PRJ-001')
            ->assertDontSee('PRJ-002')
            ->set('search', 'prj-002')
            ->assertSee('PRJ-002')
            ->assertDontSee('PRJ-001');
    }

    public function testFilterByStatus(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->ongoing()->create(['code' => 'PRJ-001']);
        Project::factory()->completed()->create(['code' => 'PRJ-002']);

        Livewire::test(Index::class)
            ->set('status', Project::STATUS_COMPLETED)
            ->assertSee('PRJ-002')
            ->assertDontSee('PRJ-001');
    }

    public function testAdminArchivesAProject(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create(['code' => 'PRJ-001']);

        Livewire::test(Index::class)
            ->call('archive', $project->id)
            ->assertHasNoErrors()
            ->assertDontSee('PRJ-001');

        $this->assertSoftDeleted($project);
    }

    public function testStaffCannotArchiveAProject(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $project = Project::factory()->create();

        Livewire::test(Index::class)
            ->call('archive', $project->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($project);
    }

    public function testTheListIsPaginatedTenRowsByDefault(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (range(1, 11) as $i) {
            Project::factory()->create(['code' => sprintf('PRJ-%03d', $i)]);
        }

        Livewire::test(Index::class)
            ->assertSee('PRJ-010')
            ->assertDontSee('PRJ-011')
            ->assertSee('Rows per page');
    }

    public function testTheProjectPageIsAPlaceholderForNow(): void
    {
        $project = Project::factory()->create(['name' => 'Riverside Townhomes']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Riverside Townhomes');

        $this->actingAs(User::factory()->worker()->create())
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function testTheStatCardsCountProjectsByStatus(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->ongoing()->count(2)->create();
        Project::factory()->onHold()->create();
        Project::factory()->planning()->create();
        Project::factory()->completed()->create()->delete();

        Livewire::test(Index::class)->assertSet('summary', [
            'total' => 4,
            'statuses' => ['planning' => 1, 'ongoing' => 2, 'on_hold' => 1, 'completed' => 0],
        ]);
    }

    public function testTheListCanSwitchBetweenCardsAndTable(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['code' => 'PRJ-001']);

        Livewire::test(Index::class)
            ->assertSet('view', 'grid')
            ->assertSee('View project')
            ->assertDontSeeHtml('<table')
            ->set('view', 'table')
            ->assertSeeHtml('<table')
            ->assertSee('PRJ-001');
    }

    public function testFiltersShowAsChipsAndCanBeCleared(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('status', Project::STATUS_ON_HOLD)
            ->assertSee('Status: On hold')
            ->call('clearFilters')
            ->assertSet('status', '')
            ->assertSet('search', '');
    }

    public function testAnEmptyListShowsTheEmptyState(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)->assertSee('No projects yet');
    }
}
