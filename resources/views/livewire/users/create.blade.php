<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Add user')"
        :description="__('The username is created from the first and last name when you save.')"
        :back="route('users.index')"
        :back-label="__('Back to users')"
    />

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
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
            <flux:button type="submit" class="btn-accent" data-test="save-user-button">{{ __('Save user') }}</flux:button>
        </div>
    </form>
</div>
