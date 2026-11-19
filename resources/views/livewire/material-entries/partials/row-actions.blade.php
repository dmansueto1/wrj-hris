{{-- Per-row menu: Edit and Archive for those who may change the entry (Admin any, Staff own entries made today). --}}
@if ($canChange($entry))
    <flux:dropdown position="bottom" align="end">
        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for this entry')" data-test="entry-actions-{{ $entry->id }}" />

        <flux:menu>
            <flux:menu.item icon="pencil-square" :href="route('material-entries.edit', $entry)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item
                icon="archive-box"
                variant="danger"
                wire:click="archive({{ $entry->id }})"
                wire:confirm="{{ __('Archive this purchase? Its cost is removed from the project.') }}"
            >
                {{ __('Archive') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
@else
    <span class="inline-block size-8" aria-hidden="true"></span>
@endif
