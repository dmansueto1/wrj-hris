<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Record material purchase')"
        :description="__('The markup is added automatically; check the total before saving.')"
        :back="route('material-entries.index')"
        :back-label="__('Back to material entries')"
    />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.material-entries.partials.entry-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('material-entries.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" class="btn-accent" data-test="save-entry-button">{{ __('Save purchase') }}</flux:button>
        </div>
    </form>
</div>
