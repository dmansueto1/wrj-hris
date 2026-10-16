<?php

namespace Tests\Feature\Projects;

use App\Livewire\Projects\Create;
use App\Livewire\Projects\Edit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectFormTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCreatesAProjectAndTheCodeIsSavedInUppercase(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillCreateForm()->call('save')->assertHasNoErrors()->assertRedirect(route('projects.index'));

        $project = Project::sole();
        $this->assertSame('ABC-01', $project->code);
        $this->assertSame('Riverside Townhomes', $project->name);
        $this->assertSame('Pasig City', $project->location);
        $this->assertSame('Acme Realty', $project->client_name);
        $this->assertSame('2026-01-05', $project->start_date->toDateString());
        $this->assertSame('2026-12-20', $project->end_date->toDateString());
        $this->assertSame('2500000.00', $project->budget);
        $this->assertSame(Project::STATUS_ONGOING, $project->status);
    }

    public function testTheCodeMustBeUniqueRegardlessOfCase(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['code' => 'ABC-01']);

        $this->fillCreateForm()->call('save')->assertHasErrors(['code' => 'unique']);
    }

    public function testAnArchivedProjectsCodeCannotBeReused(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['code' => 'ABC-01'])->delete();

        $this->fillCreateForm()->call('save')->assertHasErrors(['code' => 'unique']);
    }

    public function testAnEndDateBeforeTheStartDateFailsValidation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillCreateForm()
            ->set('start_date', '2026-06-10')
            ->set('end_date', '2026-06-09')
            ->call('save')
            ->assertHasErrors('end_date');

        $this->fillCreateForm()
            ->set('start_date', '2026-06-10')
            ->set('end_date', '2026-06-10')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function testTheBudgetMustBeZeroOrMore(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->fillCreateForm()->set('budget', '-1')->call('save')->assertHasErrors('budget');
        $this->fillCreateForm()->set('code', 'ZERO-01')->set('budget', '0')->call('save')->assertHasNoErrors();
        $this->fillCreateForm()->set('code', 'NONE-01')->set('budget', '')->call('save')->assertHasNoErrors();

        $this->assertNull(Project::where('code', 'NONE-01')->sole()->budget);
    }

    public function testRequiredFields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Create::class)
            ->set('status', '')
            ->call('save')
            ->assertHasErrors(['code' => 'required', 'name' => 'required', 'location' => 'required', 'status' => 'required']);
    }

    public function testStaffGet403OnTheCreateUrl(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('projects.create'))
            ->assertForbidden();

        Livewire::test(Create::class)->assertForbidden();
    }

    public function testAdminEditsAProject(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->planning()->create(['code' => 'ABC-01', 'name' => 'Old name']);

        Livewire::test(Edit::class, ['project' => $project])
            ->assertSet('code', 'ABC-01')
            ->set('name', 'New name')
            ->set('status', Project::STATUS_ONGOING)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('projects.index'));

        $project->refresh();
        $this->assertSame('New name', $project->name);
        $this->assertSame(Project::STATUS_ONGOING, $project->status);
    }

    public function testEditingKeepsTheCodeUniqueButIgnoresTheProjectItself(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['code' => 'ABC-01']);
        $project = Project::factory()->create(['code' => 'ABC-02']);

        Livewire::test(Edit::class, ['project' => $project])->call('save')->assertHasNoErrors();

        Livewire::test(Edit::class, ['project' => $project])
            ->set('code', 'abc-01')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function testStaffGet403OnTheEditUrl(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }

    private function fillCreateForm(): Testable
    {
        return Livewire::test(Create::class)
            ->set('code', 'abc-01')
            ->set('name', 'Riverside Townhomes')
            ->set('location', 'Pasig City')
            ->set('client_name', 'Acme Realty')
            ->set('start_date', '2026-01-05')
            ->set('end_date', '2026-12-20')
            ->set('budget', '2500000')
            ->set('status', Project::STATUS_ONGOING);
    }
}
