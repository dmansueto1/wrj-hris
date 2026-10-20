<?php

namespace App\Livewire\Materials;

use App\Livewire\Materials\Concerns\MaterialForm;
use App\Models\Material;
use App\Services\MaterialService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add material')]
class Create extends Component
{
    use MaterialForm;

    public function save(MaterialService $materials): void
    {
        $material = $materials->create($this->validateMaterial());

        Flux::toast(variant: 'success', text: __('Material :code created.', ['code' => $material->code]));

        $this->redirectRoute('materials.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.materials.create', [
            'units' => Material::UNITS,
        ]);
    }
}
