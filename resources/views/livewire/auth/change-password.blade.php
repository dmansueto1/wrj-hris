<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Set a new password')"
        :description="__('For your security, replace your temporary password before you continue.')"
    />

    <form wire:submit="changePassword" class="flex flex-col gap-6">
        <flux:input
            wire:model="current_password"
            :label="__('Current password')"
            type="password"
            required
            autofocus
            autocomplete="current-password"
            viewable
        />

        <flux:input
            wire:model="password"
            :label="__('New password')"
            :description="__('At least 12 characters, with upper and lower case letters, a number and a symbol.')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm new password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button variant="primary" type="submit" class="w-full" data-test="change-password-button">
            {{ __('Save new password') }}
        </flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
            {{ __('Log out') }}
        </button>
    </form>
</div>
