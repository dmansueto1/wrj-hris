<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="$material->code.' · '.$material->name"
        :description="__('Changing the default price does not change past material entries.')"
        :back="route('materials.index')"
        :back-label="__('Back to materials')"
    >
        <x-slot:badge>
            @include('livewire.materials.partials.status-badge', ['material' => $material])
        </x-slot:badge>
    </x-page-header>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.materials.partials.material-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('materials.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" data-test="save-material-button">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
