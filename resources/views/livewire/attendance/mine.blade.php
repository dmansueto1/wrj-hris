{{-- My Attendance (T-11, T-11.4): every day of the range, Present or Absent like All Attendance. Hours only: no peso amounts, no photos. --}}
<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Attendance')" :description="__('Your attendance by day, with the hours worked on each project.')" />

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card icon="check-circle" color="green" :label="__('Present days')" :value="number_format($this->totals['present_days'])" :note="__('Days with attendance')" />
        <x-stat-card icon="minus-circle" color="zinc" :label="__('Absent days')" :value="number_format($this->totals['absent_days'])" :note="__('Days without attendance')" />
        <x-stat-card icon="clock" color="blue" :label="__('Total hours')" :value="$this->totals['hours'].' h'" :note="__('In this date range')" />
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 sm:grid-cols-2 md:max-w-xl">
            <flux:input type="date" wire:model.live="from" :label="__('From')" data-test="filter-from" />
            <flux:input type="date" wire:model.live="to" :label="__('To')" data-test="filter-to" />
        </div>

        @if ($this->range['shortened'])
            <p class="mt-3 text-xs text-warning" data-test="range-shortened">
                {{ __('Showing the last 92 days of the range: :from to :to.', [
                    'from' => \Illuminate\Support\Carbon::parse($this->range['from'])->format('M j, Y'),
                    'to' => \Illuminate\Support\Carbon::parse($this->range['to'])->format('M j, Y'),
                ]) }}
            </p>
        @endif

        @unless ($isThisWeek)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                <x-filter-chip
                    :label="__('Dates: :from to :to', ['from' => \Illuminate\Support\Carbon::parse($from)->format('M j, Y'), 'to' => \Illuminate\Support\Carbon::parse($to)->format('M j, Y')])"
                    wire:click="thisWeek"
                />
                <button type="button" wire:click="thisWeek" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endunless
    </div>

    <x-data-card>
        @if ($this->days->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('No days on this page.')" :description="__('Go back to the first page or pick other dates.')" />
        @else
            {{-- Desktop / tablet: one row per attendance, an Absent row for days without; the total hours sit under Worked time --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Project') }}</flux:table.column>
                        <flux:table.column>{{ __('In') }}</flux:table.column>
                        <flux:table.column>{{ __('Out') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->days as $day)
                            @php($date = $day['date']->toDateString())
                            @php($records = $day['attendances']->isEmpty() ? [null] : $day['attendances']->all())
                            @foreach ($records as $attendance)
                                <flux:table.row :key="'row-'.$date.'-'.($attendance?->id ?? 'absent')">
                                    <flux:table.cell class="whitespace-nowrap">
                                        @if ($loop->first)
                                            <span class="font-semibold text-brand-navy" data-test="day-{{ $date }}">{{ $day['date']->format('l, M j') }}</span>
                                        @endif
                                    </flux:table.cell>
                                    @if ($attendance)
                                        <flux:table.cell>
                                            <span class="block max-w-64 truncate font-medium text-brand-navy">{{ $attendance->project->name }}</span>
                                            <span class="font-mono text-xs text-zinc-500">{{ $attendance->project->code }}</span>
                                        </flux:table.cell>
                                        <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }}</flux:table.cell>
                                        <flux:table.cell class="whitespace-nowrap tabular-nums">
                                            @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
                                        </flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance])</flux:table.cell>
                                    @else
                                        @foreach (range(1, 4) as $i)
                                            <flux:table.cell class="text-zinc-400">&mdash;</flux:table.cell>
                                        @endforeach
                                    @endif
                                    <flux:table.cell>
                                        @if ($loop->first)
                                            @include('livewire.attendance.partials.presence-badge', ['present' => $day['present']])
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        @endforeach

                        <flux:table.row class="bg-brand-tint/50">
                            <flux:table.cell colspan="4" class="font-semibold text-brand-navy">{{ __('Total') }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy"><span data-test="range-total">{{ $this->totals['hours'] }}</span> h</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: one card per day --}}
            <ol class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->days as $day)
                    <li wire:key="day-card-{{ $day['date']->toDateString() }}" class="flex flex-col gap-2 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-bold text-brand-navy">{{ $day['date']->format('l, M j') }}</h2>
                            @include('livewire.attendance.partials.presence-badge', ['present' => $day['present']])
                        </div>

                        @foreach ($day['attendances'] as $attendance)
                            <div wire:key="attendance-card-{{ $attendance->id }}" class="flex flex-col gap-1 rounded-xl border-s-2 border-brand-gold bg-brand-page/60 px-3 py-2.5">
                                <p class="truncate font-semibold text-brand-navy">{{ $attendance->project->name }}</p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                    <span class="tabular-nums text-zinc-700">
                                        {{ $attendance->clock_in_at->format('g:i A') }}
                                        &rarr;
                                        @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
                                    </span>
                                    <span class="font-semibold tabular-nums text-brand-navy">
                                        @include('livewire.attendance.partials.worked-time', ['attendance' => $attendance])
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 bg-brand-tint/50 p-4 text-sm">
                    <span class="font-semibold text-brand-navy">{{ __('Total') }}</span>
                    <span class="font-bold tabular-nums text-brand-navy">{{ $this->totals['hours'] }} h</span>
                </li>
            </ol>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->days" />
        </div>
    </x-data-card>
</div>
