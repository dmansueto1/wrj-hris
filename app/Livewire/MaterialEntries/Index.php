<?php

namespace App\Livewire\MaterialEntries;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectMaterialEntry;
use App\Services\MaterialEntryService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Material Entries (T-15): material purchases per project, filtered by project, material and
 * date range, with a totals row for everything that matches the filters.
 *
 * @property-read LengthAwarePaginator<int, ProjectMaterialEntry> $entries
 * @property-read array{count: int, subtotal: string, markup: string, total: string} $totals
 */
#[Title('Material Entries')]
class Index extends Component
{
    use WithPerPage;

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $material = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('material-entries.create'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['project', 'material', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('project', 'material', 'from', 'to');
        $this->resetPage();
    }

    public function archive(int $entryId, MaterialEntryService $entries): void
    {
        $entry = ProjectMaterialEntry::findOrFail($entryId);
        abort_unless($entries->canChange(Auth::user(), $entry), 403);

        $entries->archive($entry);

        unset($this->entries, $this->totals);
        Flux::toast(variant: 'success', text: __('Material purchase archived.'));
    }

    /**
     * @return LengthAwarePaginator<int, ProjectMaterialEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return app(MaterialEntryService::class)->filtered($this->filters())
            ->with(['project:id,code,name', 'material:id,code,name,unit', 'enteredBy:id,first_name,last_name,name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($this->perPage());
    }

    /**
     * @return array{count: int, subtotal: string, markup: string, total: string}
     */
    #[Computed]
    public function totals(): array
    {
        return app(MaterialEntryService::class)->totals($this->filters());
    }

    /**
     * Every project and material (archived ones too), so past entries can still be found.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projectOptions(): Collection
    {
        return Project::withTrashed()->orderBy('name')->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, Material>
     */
    #[Computed]
    public function materialOptions(): Collection
    {
        return Material::withTrashed()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function render(MaterialEntryService $entries): View
    {
        $user = Auth::user();

        return view('livewire.material-entries.index', [
            'hasFilters' => $this->project !== '' || $this->material !== '' || $this->from !== '' || $this->to !== '',
            // Who may edit or archive each row; a closure, not a public method, so it is not a callable action.
            'canChange' => fn (ProjectMaterialEntry $entry): bool => $entries->canChange($user, $entry),
        ]);
    }

    /**
     * @return array{project: string, material: string, from: string, to: string}
     */
    private function filters(): array
    {
        return ['project' => $this->project, 'material' => $this->material, 'from' => $this->from, 'to' => $this->to];
    }
}
