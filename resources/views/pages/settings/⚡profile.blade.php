<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->email = Auth::user()->email ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate(['email' => $this->emailRules($user->id)]);

        $user->fill(['email' => $validated['email'] ?: null]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input :value="auth()->user()->name" :label="__('Name')" type="text" readonly disabled />

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

            <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
