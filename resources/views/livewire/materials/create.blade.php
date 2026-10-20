<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Add material')"
        :description="__('Materials with a unit and default price, used when recording purchases.')"
        :back="route('materials.index')"
        :back-label="__('Back to materials')"
    />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.materials.partials.material-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('materials.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" class="btn-accent" data-test="save-material-button">{{ __('Save material') }}</flux:button>
        </div>
    </form>
</div>
