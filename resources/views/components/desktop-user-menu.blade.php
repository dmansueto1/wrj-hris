{{-- User card at the bottom of the sidebar: avatar, name and role, with a dropdown. --}}
<flux:dropdown position="top" align="start" {{ $attributes }}>
    <button
        type="button"
        class="flex w-full items-center gap-3 rounded-lg p-2 text-start transition-colors duration-150 hover:bg-white/[7%]"
        data-test="sidebar-menu-button"
    >
        <x-avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
        <span class="min-w-0 flex-1 in-data-flux-sidebar-collapsed-desktop:hidden">
            <span class="block truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</span>
            <span class="block truncate text-xs text-brand-blue-light">{{ auth()->user()->roleLabel() }}</span>
        </span>
        <flux:icon icon="chevrons-up-down" class="size-4 shrink-0 text-white/60 in-data-flux-sidebar-collapsed-desktop:hidden" />
    </button>

    <flux:menu>
        <x-user-menu-details />
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
