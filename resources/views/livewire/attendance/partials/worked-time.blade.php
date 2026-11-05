{{--
    Worked time: a live "2h 05m 09s" counter while Clocked In (from the clock-in time), otherwise the
    saved hours with the unit, e.g. "4.50 h". A dash when there are no hours (flagged, not clocked out).
    T-11.3: the hours count only the time inside the shift; when time was cut, the real clocked time
    shows under it, and "Outside shift" when nothing counted.
--}}
@if (($running = $attendance->runningTimeLabel()) !== null)
    <span
        x-data="runningTime({{ $attendance->clock_in_at->getTimestamp() * 1000 }})"
        class="inline-flex items-center gap-1.5 whitespace-nowrap font-semibold text-success"
        title="{{ __('Still Clocked In') }}"
        data-test="running-time"
    >
        <span class="relative flex size-2" aria-hidden="true">
            <span class="absolute inline-flex size-full rounded-full bg-success opacity-60 motion-safe:animate-ping"></span>
            <span class="relative inline-flex size-2 rounded-full bg-success"></span>
        </span>
        <span x-text="elapsed">{{ $running }}</span>
    </span>
@elseif ($attendance->hours_worked !== null)
    <span class="inline-flex flex-col items-end gap-0.5 whitespace-nowrap">
        <span data-test="worked-hours">{{ $attendance->hours_worked }} h</span>
        @if ($attendance->uncountedMinutes() > 0)
            <span
                class="text-xs text-zinc-500"
                title="{{ __('Only time inside the shift (:shift) counts.', ['shift' => $attendance->shiftLabel()]) }}"
                data-test="clocked-time"
            >{{ __('Clocked :time', ['time' => $attendance->clockedTimeLabel()]) }}</span>
            <span class="sr-only">{{ __('Only time inside the shift (:shift) counts.', ['shift' => $attendance->shiftLabel()]) }}</span>
        @endif
        @if ($attendance->isOutsideShift())
            <x-status-badge color="red" data-test="outside-shift">{{ __('Outside shift') }}</x-status-badge>
        @endif
    </span>
@else
    <span class="text-zinc-400">&mdash;</span>
@endif
