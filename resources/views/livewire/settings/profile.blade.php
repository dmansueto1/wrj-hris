<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input :value="auth()->user()->name" :label="__('Name')" type="text" readonly disabled />

            <flux:field>
                <flux:label>
                    {{ __('Username') }}
                    <span class="ms-1 text-xs font-normal text-zinc-500">({{ __('you log in with this; ask the administrator to change it') }})</span>
                </flux:label>
                <flux:input
                    :value="auth()->user()->username"
                    readonly
                    data-test="username-field"
                />
            </flux:field>

            <div>
                <flux:input wire:model="email" :label="__('Email (optional)')" type="email" autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

            <livewire:settings.delete-user-form />
    </x-pages::settings.layout>
</section>
