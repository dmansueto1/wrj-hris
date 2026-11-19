<?php

namespace App\Livewire\MaterialEntries;

use App\Livewire\MaterialEntries\Concerns\MaterialEntryForm;
use App\Services\MaterialEntryService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Record material purchase')]
class Create extends Component
{
    use MaterialEntryForm;

    public function mount(): void
    {
        $this->entry_date = today()->toDateString();
    }

    public function save(MaterialEntryService $entries): void
    {
        $entry = $entries->create(Auth::user(), $this->validateEntry());

        Flux::toast(variant: 'success', text: __('Material purchase saved: :total.', ['total' => Money::format($entry->total_cost)]));

        $this->redirectRoute('material-entries.index', navigate: true);
    }

    protected function markupRate(): string
    {
        return app(MaterialEntryService::class)->currentMarkupRate();
    }

    public function render(): View
    {
        return view('livewire.material-entries.create');
    }
}
