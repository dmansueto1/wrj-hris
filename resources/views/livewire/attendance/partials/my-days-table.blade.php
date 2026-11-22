{{--
    One user's attendance day by day (T-11.4): Morning / Afternoon In and Out, worked time, remarks
    and Present / Absent / Rest Day, hours only (no peso amounts, no photos), with cards on phones.
    Used by My Attendance and the Dashboard (T-15.1).
    $days: AttendanceHistoryService::employeeDays() rows. $totalHours: "12.50" for a Total row, or null.
    $totalLate (T-16.1): the range's Late, "0.42", shown on the Total row; null for none.
--}}
{{-- Desktop / tablet: one row per day; the attendance under Morning and Afternoon; the total hours sit under Worked time --}}
<div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
    <flux:table>
        <flux:table.columns sticky class="bg-white">
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Morning In') }}</flux:table.column>
            <flux:table.column>{{ __('Morning Out') }}</flux:table.column>
            <flux:table.column>{{ __('Afternoon In') }}</flux:table.column>
            <flux:table.column>{{ __('Afternoon Out') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Late') }}</flux:table.column>
            <flux:table.column class="w-32">{{ __('Remarks') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($days as $day)
                @php($date = $day['date']->toDateString())
                <flux:table.row :key="'row-'.$date">
                    <flux:table.cell class="whitespace-nowrap">
                        <span class="font-semibold text-brand-navy" data-test="day-{{ $date }}">{{ $day['date']->format('l, M j') }}</span>
                    </flux:table.cell>
                    @php($attendanceDay = \App\Support\AttendanceDay::of($day['attendances']))
                    @include('livewire.attendance.partials.day-cells', ['day' => $attendanceDay, 'photos' => false])
                    <flux:table.cell class="w-32">@include('livewire.attendance.partials.day-remarks', ['day' => $attendanceDay])</flux:table.cell>
                    <flux:table.cell>
                        @include('livewire.attendance.partials.presence-badge', ['present' => $day['present'], 'restDay' => $day['rest_day'], 'future' => $day['future']])
                    </flux:table.cell>
                </flux:table.row>
            @endforeach

            @if ($totalHours !== null)
                <flux:table.row class="bg-brand-tint/50">
                    <flux:table.cell colspan="5" class="font-semibold text-brand-navy">{{ __('Total') }}</flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy"><span data-test="range-total">{{ $totalHours }}</span> h</flux:table.cell>
                    <flux:table.cell align="end">
                        @if (($totalLate ?? null) !== null)
                            @include('livewire.attendance.partials.late-badge', ['hours' => $totalLate, 'test' => 'range-total-late'])
                        @endif
                    </flux:table.cell>
                    <flux:table.cell colspan="2"></flux:table.cell>
                </flux:table.row>
            @endif
        </flux:table.rows>
    </flux:table>
</div>

{{-- Phone: one card per day --}}
<ol class="divide-y divide-zinc-100 md:hidden">
    @foreach ($days as $day)
        <li wire:key="day-card-{{ $day['date']->toDateString() }}" class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold text-brand-navy">{{ $day['date']->format('l, M j') }}</h2>
                @include('livewire.attendance.partials.presence-badge', ['present' => $day['present'], 'restDay' => $day['rest_day'], 'future' => $day['future']])
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
            @include('livewire.attendance.partials.late-line', ['day' => \App\Support\AttendanceDay::of($day['attendances'])])
        </li>
    @endforeach
    @if ($totalHours !== null)
        <li class="flex items-center justify-between gap-3 bg-brand-tint/50 p-4 text-sm">
            <span class="font-semibold text-brand-navy">{{ __('Total') }}</span>
            <span class="text-end tabular-nums">
                <span class="block font-bold text-brand-navy">{{ $totalHours }} h</span>
                @if (($totalLate ?? null) !== null)
                    <span class="mt-1 block">@include('livewire.attendance.partials.late-badge', ['hours' => $totalLate, 'label' => true])</span>
                @endif
            </span>
        </li>
    @endif
</ol>
