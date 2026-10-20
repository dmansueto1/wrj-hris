<?php

namespace App\Livewire\Materials;

use App\Livewire\Materials\Concerns\MaterialForm;
use App\Models\Material;
use App\Services\MaterialService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit material')]
class Edit extends Component
{
    use MaterialForm;

    public Material $material;

    public function mount(Material $material): void
    {
        $this->material = $material;
        $this->fillFromMaterial($material);
    }

    public function save(MaterialService $materials): void
    {
        $materials->update($this->material, $this->validateMaterial($this->material->id));

        Flux::toast(variant: 'success', text: __('Material :code updated.', ['code' => $this->material->code]));

        $this->redirectRoute('materials.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.materials.edit', [
            'units' => Material::UNITS,
        ]);
    }
}
