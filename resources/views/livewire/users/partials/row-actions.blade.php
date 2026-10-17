{{-- Per-row menu: open the user, or deactivate / reactivate (not on your own account). --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $user->name])" data-test="user-actions-{{ $user->id }}" />

    <flux:menu>
        <flux:menu.item icon="eye" :href="route('users.edit', $user)" wire:navigate>{{ __('View') }}</flux:menu.item>
        <flux:menu.item icon="pencil-square" :href="route('users.edit', $user)" wire:navigate>{{ __('Edit') }}</flux:menu.item>

        @unless ($user->is(auth()->user()))
            <flux:menu.separator />
            @if ($user->status === \App\Models\User::STATUS_ACTIVE)
                <flux:menu.item
                    icon="no-symbol"
                    variant="danger"
                    wire:click="deactivate({{ $user->id }})"
                    wire:confirm="{{ __('Deactivate :name? They will not be able to log in.', ['name' => $user->name]) }}"
                >
                    {{ __('Deactivate') }}
                </flux:menu.item>
            @else
                <flux:menu.item icon="check-circle" wire:click="reactivate({{ $user->id }})">{{ __('Reactivate') }}</flux:menu.item>
            @endif
        @endunless
    </flux:menu>
</flux:dropdown>
