{{--
    Remarks for one person's day, one short item (T-11.3): "Outside shift" when none of it counted (the
    clocked time in its tooltip), otherwise "Clocked 4h 34m" when some time was outside the shift.
--}}
<div class="whitespace-nowrap" data-test="remarks">
    @if ($day->isOutsideShift())
        <x-status-badge
            color="red"
            title="{{ __('Clocked :time. Only time inside the shift (:shift) counts.', ['time' => $day->clockedLabel(), 'shift' => $day->shiftLabel()]) }}"
            data-test="outside-shift"
        >{{ __('Outside shift') }}</x-status-badge>
    @elseif ($day->uncountedMinutes() > 0)
        <span class="text-xs text-zinc-500" title="{{ __('Only time inside the shift (:shift) counts.', ['shift' => $day->shiftLabel()]) }}" data-test="clocked-time">
            {{ __('Clocked :time', ['time' => $day->clockedLabel()]) }}
        </span>
        <span class="sr-only">{{ __('Only time inside the shift (:shift) counts.', ['shift' => $day->shiftLabel()]) }}</span>
    @endif
</div>
