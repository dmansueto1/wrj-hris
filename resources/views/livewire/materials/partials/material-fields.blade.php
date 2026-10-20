{{-- Fields shared by the create and edit material forms. --}}
<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
    <flux:input wire:model="code" :label="__('Code')" :placeholder="__('e.g. HB-04')" required maxlength="30" class="uppercase" />

    <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Portland cement 40kg')" required maxlength="255" />

    <flux:select wire:model="unit" :label="__('Unit')" required>
        @foreach ($units as $unitOption)
            <flux:select.option :value="$unitOption">{{ $unitOption }}</flux:select.option>
        @endforeach
    </flux:select>

    {{-- The hint sits on the label line so the input lines up with the fields beside it. --}}
    <flux:field>
        <flux:label>
            {{ __('Default price (₱)') }}
            <span class="ms-1 text-xs font-normal text-zinc-500">({{ __('can be changed per entry') }})</span>
        </flux:label>
        <flux:input wire:model="default_price" inputmode="decimal" placeholder="0.00" required />
        <flux:error name="default_price" />
    </flux:field>
</div>
