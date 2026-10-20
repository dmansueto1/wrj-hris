{{-- Per-row menu: Edit, Deactivate / Reactivate, Archive. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :code', ['code' => $material->code])" data-test="material-actions-{{ $material->id }}" />

    <flux:menu>
        <flux:menu.item icon="pencil-square" :href="route('materials.edit', $material)" wire:navigate>{{ __('Edit') }}</flux:menu.item>

        @if ($material->status === \App\Models\Material::STATUS_ACTIVE)
            <flux:menu.item
                icon="no-symbol"
                wire:click="deactivate({{ $material->id }})"
                wire:confirm="{{ __('Deactivate :name? It can no longer be picked for new entries.', ['name' => $material->name]) }}"
            >
                {{ __('Deactivate') }}
            </flux:menu.item>
        @else
            <flux:menu.item icon="check-circle" wire:click="reactivate({{ $material->id }})">{{ __('Reactivate') }}</flux:menu.item>
        @endif

        <flux:menu.separator />
        <flux:menu.item
            icon="archive-box"
            variant="danger"
            wire:click="archive({{ $material->id }})"
            wire:confirm="{{ __('Archive :name? Past material entries are kept.', ['name' => $material->name]) }}"
        >
            {{ __('Archive') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
