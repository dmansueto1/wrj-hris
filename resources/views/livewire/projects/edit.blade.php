<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="$project->code.' · '.$project->name"
        :description="__('Workers can only clock in to projects with status Ongoing.')"
        :back="route('projects.index')"
        :back-label="__('Back to projects')"
    >
        <x-slot:badge>
            <x-status-badge :color="$project->statusColor()">{{ __($project->statusLabel()) }}</x-status-badge>
        </x-slot:badge>
    </x-page-header>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        @include('livewire.projects.partials.project-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('projects.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" data-test="save-project-button">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
