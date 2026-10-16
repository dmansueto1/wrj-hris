{{-- Fields shared by the create and edit user forms. --}}
<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <flux:input wire:model="employee_no" :label="__('Employee no.')" required maxlength="30" />

    <flux:select wire:model="role" :label="__('Role')" required>
        @foreach ($roles as $roleOption)
            <flux:select.option :value="$roleOption">{{ __(ucfirst($roleOption)) }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="email" :label="__('Email (optional)')" type="email" maxlength="255" />

    <flux:input wire:model.live.blur="first_name" :label="__('First name')" required maxlength="100" />

    <flux:input wire:model.live.blur="last_name" :label="__('Last name')" required maxlength="100" />

    {{-- Read-only: first letter of the first name + last name, e.g. jdoe (jdoe2 if taken).
         The hint sits on the label line so the input lines up with the fields beside it. --}}
    <flux:field>
        <flux:label>
            {{ __('Username') }}
            <span class="ms-1 text-xs font-normal text-zinc-500">({{ $usernameHint }})</span>
        </flux:label>
        <flux:input
            :value="$username"
            :placeholder="__('e.g. jdoe')"
            readonly
            class="bg-brand-tint"
            data-test="username-field"
        />
    </flux:field>

    <flux:input wire:model="position" :label="__('Position')" maxlength="255" />

    <flux:input
        wire:model="hourly_rate"
        :label="__('Hourly rate (₱)')"
        inputmode="decimal"
        placeholder="0.00"
        required
    />
</div>
