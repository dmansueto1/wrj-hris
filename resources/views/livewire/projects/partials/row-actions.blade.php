{{-- Admin-only menu on a project row or card: Edit and Archive.
     $testIds marks the copy that carries the data-test hooks (the list renders cards and the table). --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :code', ['code' => $project->code])" />

    <flux:menu>
        <flux:menu.item icon="eye" :href="route('projects.show', $project)" wire:navigate>{{ __('View') }}</flux:menu.item>
        <flux:menu.item
            icon="pencil-square"
            :href="route('projects.edit', $project)"
            wire:navigate
            :data-test="$testIds ? 'edit-project-'.$project->id : null"
        >
            {{ __('Edit') }}
        </flux:menu.item>
        <flux:menu.separator />
        <flux:menu.item
            icon="archive-box"
            variant="danger"
            wire:click="archive({{ $project->id }})"
            wire:confirm="{{ __('Archive :code? Its attendance and material records are kept.', ['code' => $project->code]) }}"
            :data-test="$testIds ? 'archive-project-'.$project->id : null"
        >
            {{ __('Archive') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
