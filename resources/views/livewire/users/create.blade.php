<div class="flex w-full flex-col gap-6">
    <div>
        <flux:link :href="route('users.index')" wire:navigate class="text-sm">&larr; {{ __('Back to users') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Add user') }}</flux:heading>
        <flux:subheading>{{ __('The username is created from the first and last name when you save.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6">
        @include('livewire.users.partials.user-fields')

        <flux:input
            wire:model="password"
            :label="__('Temporary password')"
            :description="__('The user must change it at first login. At least 12 characters, with upper and lower case letters, a number and a symbol.')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <div class="flex justify-end gap-3">
            <flux:button :href="route('users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" data-test="save-user-button">{{ __('Save user') }}</flux:button>
        </div>
    </form>
</div>
