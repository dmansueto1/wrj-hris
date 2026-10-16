<div class="flex w-full flex-col gap-6">
    <div>
        <flux:link :href="route('projects.index')" wire:navigate class="text-sm">&larr; {{ __('Back to projects') }}</flux:link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl" level="1">{{ $project->code }} &middot; {{ $project->name }}</flux:heading>
            <flux:badge size="sm" :color="$project->statusColor()">{{ __($project->statusLabel()) }}</flux:badge>
        </div>

        <flux:subheading>{{ __('Workers can only clock in to projects with status Ongoing.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6">
        @include('livewire.projects.partials.project-fields')

        <div class="flex justify-end gap-3">
            <flux:button :href="route('projects.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" data-test="save-project-button">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
