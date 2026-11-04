{{-- Shift card on the user edit page (T-11.2): shift type, Monday-to-Sunday In / Out times and rest days. --}}
<section class="flex flex-col gap-4 rounded-card bg-white p-6 shadow-card ring-1 ring-brand-navy/5" data-test="shift-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="lg" level="2">{{ __('Shift') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The hours this employee is expected to work each day.') }}</flux:text>
        </div>
    </div>

    @include('livewire.users.partials.shift-fields')

    <div class="flex justify-end">
        <flux:button type="button" wire:click="save" variant="primary" data-test="save-shift-button">{{ __('Save shift') }}</flux:button>
    </div>
</section>
