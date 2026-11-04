{{-- Shift editor (T-11.2), shared by Add user and the user's Shift card: type, Monday-to-Sunday times and rest days.
     The component uses App\Livewire\Users\Concerns\EditsShift and passes $weekdays and $columns. --}}
<div class="sm:max-w-sm">
    <flux:select wire:model.live="type" :label="__('Shift type')" data-test="shift-type">
        @foreach (\App\Models\User::SHIFT_TYPES as $value => $label)
            <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
        @endforeach
    </flux:select>
</div>

@if ($type === \App\Models\User::SHIFT_FLEXIBLE)
    <flux:callout variant="secondary" icon="information-circle" :heading="__('Hours count exactly as clocked in and out.')" />
@else
    @error('days')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" data-test="shift-days-error" />
    @enderror

    {{-- Desktop / tablet: a fixed 7-row form (not paginated) --}}
    <div class="hidden overflow-x-auto md:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Day') }}</flux:table.column>
                @foreach ($columns as $label)
                    <flux:table.column>{{ $label }}</flux:table.column>
                @endforeach
                <flux:table.column>{{ __('Rest day') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($weekdays as $weekday => $dayName)
                    @php($rest = (bool) ($days[$weekday]['is_rest_day'] ?? false))
                    <flux:table.row :key="'shift-day-'.$weekday" :class="$rest ? 'bg-neutral-soft/60' : ''">
                        <flux:table.cell class="whitespace-nowrap font-semibold text-brand-navy">{{ __($dayName) }}</flux:table.cell>

                        @if ($rest)
                            <flux:table.cell :colspan="count($columns)">
                                <x-status-badge color="zinc">{{ __('Rest day') }}</x-status-badge>
                            </flux:table.cell>
                        @else
                            @foreach ($columns as $key => $label)
                                <flux:table.cell>
                                    <flux:input
                                        type="time"
                                        size="sm"
                                        wire:model="days.{{ $weekday }}.{{ $key }}"
                                        :aria-label="__(':time on :day', ['time' => $label, 'day' => __($dayName)])"
                                        class="w-32"
                                    />
                                    <flux:error :name="'days.'.$weekday.'.'.$key" />
                                </flux:table.cell>
                            @endforeach
                        @endif

                        <flux:table.cell>
                            <flux:checkbox wire:model.live="days.{{ $weekday }}.is_rest_day" :label="__('Rest day')" data-test="rest-day-{{ $weekday }}" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Phone: one card per day --}}
    <ul class="flex flex-col gap-3 md:hidden">
        @foreach ($weekdays as $weekday => $dayName)
            @php($rest = (bool) ($days[$weekday]['is_rest_day'] ?? false))
            <li wire:key="shift-card-{{ $weekday }}" @class(['rounded-xl border border-zinc-100 p-3', 'bg-neutral-soft/60' => $rest])>
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold text-brand-navy">{{ __($dayName) }}</span>
                    <flux:checkbox wire:model.live="days.{{ $weekday }}.is_rest_day" :label="__('Rest day')" />
                </div>

                @if ($rest)
                    <x-status-badge color="zinc" class="mt-2">{{ __('Rest day') }}</x-status-badge>
                @else
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        @foreach ($columns as $key => $label)
                            <flux:field>
                                <flux:label>{{ $label }}</flux:label>
                                <flux:input type="time" size="sm" wire:model="days.{{ $weekday }}.{{ $key }}" />
                                <flux:error :name="'days.'.$weekday.'.'.$key" />
                            </flux:field>
                        @endforeach
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-zinc-600" data-test="shift-summary">{{ $this->summary }}</p>
        <button type="button" wire:click="copyMonday" class="text-sm font-medium text-brand-blue hover:text-brand-blue-dark hover:underline" data-test="copy-monday">
            {{ __('Copy Monday to all working days') }}
        </button>
    </div>
@endif
