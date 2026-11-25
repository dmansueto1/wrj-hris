<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Edit material purchase')"
        :description="__('This entry keeps the markup rate it was saved with.')"
        :back="route('material-entries.index')"
        :back-label="__('Back to material entries')"
    />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.material-entries.partials.entry-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('material-entries.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" data-test="save-entry-button">{{ __('Save changes') }}</flux:button>
        </div>
    </form>

    @can('audit.view')
        <x-audit-history :entries="$this->auditHistory" class="rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5" />
    @endcan
</div>
