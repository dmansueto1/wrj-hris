{{-- By employee tab (T-11.4): one employee's attendance day by day over a date range, Absent days included. --}}
{{-- Filters --}}
<div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
    {{-- One row from tablet width up: the employee takes the free space, the two dates a fixed width. --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="min-w-0 sm:max-w-sm sm:flex-1">
            <flux:select wire:model.live="employee" :placeholder="__('Select employee')" :aria-label="__('Employee')" data-test="filter-employee">
                @foreach ($this->users as $person)
                    <flux:select.option :value="$person->id">{{ $person->listName() }} ({{ $person->employee_no }})</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="sm:w-44 sm:shrink-0">
            <flux:input type="date" wire:model.live="from" :aria-label="__('From')" data-test="filter-from" />
        </div>
        <div class="sm:w-44 sm:shrink-0">
            <flux:input type="date" wire:model.live="to" :aria-label="__('To')" data-test="filter-to" />
        </div>
    </div>

    @if ($this->range['shortened'])
        <p class="mt-3 text-xs text-warning" data-test="range-shortened">
            {{ __('Showing the last 92 days of the range: :from to :to.', [
                'from' => \Illuminate\Support\Carbon::parse($this->range['from'])->format('M j, Y'),
                'to' => \Illuminate\Support\Carbon::parse($this->range['to'])->format('M j, Y'),
            ]) }}
        </p>
    @endif

    @if ($employee !== '' || [$from, $to] !== $thisWeek)
        <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
            @if ($employee !== '')
                <x-filter-chip :label="__('Employee: :value', ['value' => $this->users->firstWhere('id', (int) $employee)?->listName()])" wire:click="$set('employee', '')" />
            @endif
            @if ([$from, $to] !== $thisWeek)
                <x-filter-chip
                    :label="__('Dates: :from to :to', ['from' => \Illuminate\Support\Carbon::parse($from)->format('M j, Y'), 'to' => \Illuminate\Support\Carbon::parse($to)->format('M j, Y')])"
                    wire:click="$set('from', '{{ $thisWeek[0] }}'); $set('to', '{{ $thisWeek[1] }}')"
                />
            @endif
            <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                {{ __('Clear filters') }}
            </button>
        </div>
    @endif
</div>

@if ($this->employeeDays === null)
    <x-data-card>
        <x-empty-state
            icon="user"
            :title="__('Select an employee to see their attendance.')"
            :description="__('Their attendance for each day of the date range shows here, with the days they were absent.')"
            data-test="choose-employee"
        />
    </x-data-card>
@else
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card icon="check-circle" color="green" :label="__('Present days')" :value="number_format($this->employeeSummary['present_days'])" :note="__('Days with attendance')" />
        <x-stat-card icon="minus-circle" color="zinc" :label="__('Absent days')" :value="number_format($this->employeeSummary['absent_days'])" :note="__('Working days without attendance')" />
        <x-stat-card icon="clock" color="blue" :label="__('Total hours')" :value="$this->employeeSummary['hours'].' h'" :note="__('In this date range')" />
    </div>

    <x-data-card>
        {{-- Desktop / tablet: one row per day; the attendance under Morning and Afternoon --}}
        <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
            <flux:table>
                <flux:table.columns sticky class="bg-white">
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Morning In') }}</flux:table.column>
                    <flux:table.column>{{ __('Morning Out') }}</flux:table.column>
                    <flux:table.column>{{ __('Afternoon In') }}</flux:table.column>
                    <flux:table.column>{{ __('Afternoon Out') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
                    <flux:table.column>{{ __('Photos') }}</flux:table.column>
                    <flux:table.column class="w-32">{{ __('Remarks') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    @can('attendance.correct')
                        <flux:table.column align="end"><span>{{ __('Actions') }}</span></flux:table.column>
                    @endcan
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->employeeDays as $day)
                        <flux:table.row :key="'day-'.$day['date']->toDateString()" :data-test="'day-row-'.$day['date']->toDateString()">
                            <flux:table.cell class="whitespace-nowrap">
                                <span class="font-semibold text-brand-navy">{{ $day['date']->format('D, M j') }}</span>
                            </flux:table.cell>
                            @php($attendanceDay = \App\Support\AttendanceDay::of($day['attendances']))
                            @include('livewire.attendance.partials.day-cells', ['day' => $attendanceDay, 'photos' => true])
                            <flux:table.cell class="w-32">@include('livewire.attendance.partials.day-remarks', ['day' => $attendanceDay])</flux:table.cell>
                            <flux:table.cell>
                                @include('livewire.attendance.partials.presence-badge', ['present' => $day['present'], 'restDay' => $day['rest_day'], 'future' => $day['future']])
                            </flux:table.cell>
                            @can('attendance.correct')
                                <flux:table.cell align="end">
                                    @include('livewire.attendance.partials.correct-menu', ['attendances' => $day['attendances'], 'key' => $day['date']->toDateString()])
                                </flux:table.cell>
                            @endcan
                        </flux:table.row>
                    @endforeach

                    <flux:table.row class="bg-brand-tint/50">
                        <flux:table.cell colspan="5" class="font-semibold text-brand-navy">{{ __('Total') }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy"><span data-test="employee-total-hours">{{ $this->employeeSummary['hours'] }} h</span></flux:table.cell>
                        <flux:table.cell :colspan="auth()->user()->can('attendance.correct') ? 4 : 3"></flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Phone: one card per day --}}
        <ul class="divide-y divide-zinc-100 md:hidden">
            @foreach ($this->employeeDays as $day)
                <li wire:key="day-card-{{ $day['date']->toDateString() }}" class="flex flex-col gap-2 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-brand-navy">{{ $day['date']->format('l, M j') }}</span>
                        <div class="flex shrink-0 items-center gap-1">
                            @include('livewire.attendance.partials.presence-badge', ['present' => $day['present'], 'restDay' => $day['rest_day'], 'future' => $day['future']])
                            @include('livewire.attendance.partials.correct-menu', ['attendances' => $day['attendances'], 'key' => $day['date']->toDateString()])
                        </div>
                    </div>
                    @foreach ($day['attendances'] as $attendance)
                        @include('livewire.attendance.partials.attendance-line', ['attendance' => $attendance])
                    @endforeach
                </li>
            @endforeach
            <li class="flex items-center justify-between gap-3 bg-brand-tint/50 p-4 text-sm">
                <span class="font-semibold text-brand-navy">{{ __('Total') }}</span>
                <span class="font-bold tabular-nums text-brand-navy">{{ $this->employeeSummary['hours'] }} h</span>
            </li>
        </ul>

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->employeeDays" />
        </div>
    </x-data-card>
@endif
