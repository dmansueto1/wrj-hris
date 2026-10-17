<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Add project')"
        :description="__('Workers can only clock in to projects with status Ongoing.')"
        :back="route('projects.index')"
        :back-label="__('Back to projects')"
    />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.projects.partials.project-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('projects.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" class="btn-accent" data-test="save-project-button">{{ __('Save project') }}</flux:button>
        </div>
    </form>
</div>
