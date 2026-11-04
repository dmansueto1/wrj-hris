<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Add user')"
        :description="__('The username is created from the first and last name when you save.')"
        :back="route('users.index')"
        :back-label="__('Back to users')"
    />

    <form wire:submit="save" class="flex flex-col gap-6">
        <section class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
            <flux:heading size="lg" level="2">{{ __('Details') }}</flux:heading>

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
        </section>

        {{-- T-11.2: the shift is set while creating the user; it can be changed later on the user's page. --}}
        <section class="flex flex-col gap-4 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5" data-test="shift-card">
            <div>
                <flux:heading size="lg" level="2">{{ __('Shift') }}</flux:heading>
                <flux:text class="mt-1">{{ __('The hours this employee is expected to work each day. Starts on Two shifts a day; change it as needed.') }}</flux:text>
            </div>

            @include('livewire.users.partials.shift-fields')
        </section>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" class="btn-accent" data-test="save-user-button">{{ __('Save user') }}</flux:button>
        </div>
    </form>
</div>
