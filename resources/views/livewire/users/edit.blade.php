<div class="flex w-full flex-col gap-6">
    <x-page-header
        :title="$user->name"
        :description="__(':role · :employee_no', ['role' => $user->roleLabel() ?: __('No role'), 'employee_no' => $user->employee_no])"
        :back="route('users.index')"
        :back-label="__('Back to users')"
    >
        <x-slot:badge>
            @include('livewire.users.partials.status-badge', ['user' => $user])
        </x-slot:badge>
        <x-slot:actions>
            <x-avatar :name="$user->name" :initials="$user->initials()" size="lg" />
        </x-slot:actions>
    </x-page-header>

    @error('status')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
    @enderror

    <form wire:submit="save" class="flex flex-col gap-6 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        <flux:heading size="lg" level="2">{{ __('Details') }}</flux:heading>

        @include('livewire.users.partials.user-fields')

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit" data-test="save-user-button">{{ __('Save changes') }}</flux:button>
        </div>
    </form>

    <section class="flex flex-col gap-4 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        <flux:heading size="lg" level="2">{{ __('Rate history') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="end">{{ __('Rate') }}</flux:table.column>
                <flux:table.column>{{ __('Effective from') }}</flux:table.column>
                <flux:table.column>{{ __('Changed by') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->rateHistories as $history)
                    <flux:table.row :key="$history->id">
                        <flux:table.cell align="end" variant="strong">{{ \App\Support\Money::format($history->hourly_rate) }}</flux:table.cell>
                        <flux:table.cell>{{ $history->effective_from->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $history->changedBy?->name ?? __('System') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <x-table-footer :paginator="$this->rateHistories" />
    </section>

    <section class="flex flex-col gap-4 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5">
        <flux:heading size="lg" level="2">{{ __('Account actions') }}</flux:heading>

        <div class="flex flex-wrap gap-3">
            <flux:modal.trigger name="reset-password">
                <flux:button icon="key">{{ __('Reset password') }}</flux:button>
            </flux:modal.trigger>

            @unless ($isSelf)
                @if ($user->status === \App\Models\User::STATUS_ACTIVE)
                    <flux:button icon="no-symbol" wire:click="deactivate" wire:confirm="{{ __('Deactivate this user? They will not be able to log in.') }}">
                        {{ __('Deactivate') }}
                    </flux:button>
                @else
                    <flux:button icon="check-circle" wire:click="reactivate">{{ __('Reactivate') }}</flux:button>
                @endif

                <flux:button variant="danger" icon="archive-box" wire:click="archive" wire:confirm="{{ __('Archive this user? Their attendance and records are kept.') }}">
                    {{ __('Archive') }}
                </flux:button>
            @endunless
        </div>

        @if ($isSelf)
            <flux:text class="text-sm">{{ __('You cannot deactivate, archive or remove the admin role from your own account.') }}</flux:text>
        @endif
    </section>

    <flux:modal name="reset-password" class="max-w-md">
        <form wire:submit="resetPassword" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Reset password') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Set a temporary password. :name must change it at their next login.', ['name' => $user->first_name]) }}</flux:text>
            </div>

            <flux:input
                wire:model="new_password"
                :label="__('Temporary password')"
                :description="__('At least 12 characters, with upper and lower case letters, a number and a symbol.')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button>{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Set temporary password') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
