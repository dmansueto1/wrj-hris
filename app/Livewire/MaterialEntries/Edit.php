<?php

namespace App\Livewire\MaterialEntries;

use App\Livewire\MaterialEntries\Concerns\MaterialEntryForm;
use App\Models\ProjectMaterialEntry;
use App\Services\AuditLogService;
use App\Services\MaterialEntryService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Change a material entry (T-15): Admin any entry, Staff only their own entries made today.
 * The entry keeps the markup rate it was saved with.
 */
#[Title('Edit material purchase')]
class Edit extends Component
{
    use MaterialEntryForm;

    #[Locked]
    public ProjectMaterialEntry $materialEntry;

    public function mount(ProjectMaterialEntry $materialEntry): void
    {
        $this->materialEntry = $materialEntry;
        $this->authorizeChange();

        $this->entry_date = $materialEntry->entry_date->toDateString();
        $this->project_id = (string) $materialEntry->project_id;
        $this->material_id = (string) $materialEntry->material_id;
        $this->quantity = $materialEntry->quantity;
        $this->unit_price = $materialEntry->unit_price;
        $this->remarks = (string) $materialEntry->remarks;
    }

    public function save(MaterialEntryService $entries): void
    {
        $this->authorizeChange();

        $entries->update($this->materialEntry, $this->validateEntry());

        Flux::toast(variant: 'success', text: __('Material purchase updated.'));

        $this->redirectRoute('material-entries.index', navigate: true);
    }

    /**
     * The entry's audit log (T-17), Admin only.
     *
     * @return Collection<int, array{at: Carbon, by: string, action: string, changes: list<array{label: string, old: string, new: string}>, reason: string|null}>
     */
    #[Computed]
    public function auditHistory(): Collection
    {
        abort_unless(Auth::user()?->can('audit.view'), 403);

        return app(AuditLogService::class)->forMaterialEntry($this->materialEntry);
    }

    protected function markupRate(): string
    {
        return $this->materialEntry->markup_rate;
    }

    protected function keptProjectId(): ?int
    {
        return $this->materialEntry->project_id;
    }

    protected function keptMaterialId(): ?int
    {
        return $this->materialEntry->material_id;
    }

    private function authorizeChange(): void
    {
        abort_unless(app(MaterialEntryService::class)->canChange(Auth::user(), $this->materialEntry), 403);
    }

    public function render(): View
    {
        return view('livewire.material-entries.edit');
    }
}
