{{-- Per-row menu: Approve (pending or blocked), Rename, Block, Archive. --}}
@php($label = $device->name ?? $device->code)
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :code', ['code' => $device->code])" data-test="device-actions-{{ $device->id }}" />

    <flux:menu>
        @if ($device->status !== \App\Models\Device::STATUS_APPROVED)
            <flux:menu.item icon="check-circle" wire:click="openApprove({{ $device->id }})">{{ __('Approve') }}</flux:menu.item>
        @endif

        <flux:menu.item icon="pencil-square" wire:click="openRename({{ $device->id }})">{{ __('Rename') }}</flux:menu.item>

        @if ($device->status !== \App\Models\Device::STATUS_BLOCKED)
            <flux:menu.item
                icon="no-symbol"
                wire:click="block({{ $device->id }})"
                wire:confirm="{{ __('Block :name? It can no longer use the kiosk or Clock In/Out.', ['name' => $label]) }}"
            >
                {{ __('Block') }}
            </flux:menu.item>
        @endif

        <flux:menu.separator />
        <flux:menu.item
            icon="archive-box"
            variant="danger"
            wire:click="archive({{ $device->id }})"
            wire:confirm="{{ __('Archive :name? It will have to request access again.', ['name' => $label]) }}"
        >
            {{ __('Archive') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
