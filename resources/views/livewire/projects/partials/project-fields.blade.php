{{-- Fields shared by the create and edit project forms. --}}
<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <flux:input wire:model="code" :label="__('Code')" :placeholder="__('e.g. PRJ-001')" required maxlength="30" class="uppercase" />

    <flux:input wire:model="name" :label="__('Name')" required maxlength="255" />

    <flux:select wire:model="status" :label="__('Status')" required>
        @foreach ($statuses as $value => [$label])
            <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="location" :label="__('Location')" required maxlength="255" />

    <flux:input wire:model="client_name" :label="__('Client (optional)')" maxlength="255" />

    <flux:input wire:model="budget" :label="__('Budget (₱, optional)')" inputmode="decimal" placeholder="0.00" />

    <flux:input wire:model="start_date" :label="__('Start date')" type="date" />

    <flux:input wire:model="end_date" :label="__('End date')" type="date" />
</div>
