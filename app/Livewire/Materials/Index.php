<?php

namespace App\Livewire\Materials;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Material;
use App\Services\MaterialService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Materials')]
class Index extends Component
{
    use WithPerPage;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /**
     * Runs on every request (first render and every action), so the permission is re-checked each time.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('materials.manage'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function deactivate(int $materialId, MaterialService $materials): void
    {
        $materials->deactivate(Material::findOrFail($materialId));

        $this->refreshList(__('Material deactivated. It can no longer be picked for new entries.'));
    }

    public function reactivate(int $materialId, MaterialService $materials): void
    {
        $materials->reactivate(Material::findOrFail($materialId));

        $this->refreshList(__('Material reactivated.'));
    }

    public function archive(int $materialId, MaterialService $materials): void
    {
        $materials->archive(Material::findOrFail($materialId));

        $this->refreshList(__('Material archived.'));
    }

    /**
     * Counts for the stat cards.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    #[Computed]
    public function summary(): array
    {
        return app(MaterialService::class)->summary();
    }

    /**
     * @return LengthAwarePaginator<int, Material>
     */
    #[Computed]
    public function materials(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Material::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($this->status, [Material::STATUS_ACTIVE, Material::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $this->status))
            ->orderBy('code')
            ->paginate($this->perPage());
    }

    public function render(): View
    {
        return view('livewire.materials.index', [
            'hasFilters' => trim($this->search) !== '' || $this->status !== '',
        ]);
    }

    private function refreshList(string $message): void
    {
        unset($this->materials, $this->summary);
        Flux::toast(variant: 'success', text: $message);
    }
}
