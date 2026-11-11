{{--
    Remarks for one person's day, one short item (T-11.3): "Outside shift" when none of it counted (the
    clocked time in its tooltip), otherwise "Clocked 4h 34m" when some time was outside the shift.
    "Imported" when any of the day's attendance came from an uploaded file (T-12.1).
    "Flagged" while an attendance waits for the Admin's correction (T-13); "Flagged, corrected" once it is corrected.
--}}
<div class="flex flex-wrap items-center gap-1 whitespace-nowrap" data-test="remarks">
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
    @if ($day->all()->contains('status', \App\Models\Attendance::STATUS_FLAGGED))
        <x-status-badge
            color="red"
            :dot="false"
            :title="__('Not clocked out. Waiting for the Admin to correct it.')"
            data-test="remark-flagged"
        >{{ __('Flagged') }}</x-status-badge>
    @endif
    @if ($day->all()->contains->wasFlagged())
        <x-status-badge
            color="indigo"
            :dot="false"
            :title="__('Was not clocked out and was flagged. The Admin corrected it.')"
            data-test="remark-was-flagged"
        >{{ __('Flagged, corrected') }}</x-status-badge>
    @endif
    @if ($day->all()->contains->isImported())
        @include('livewire.attendance.partials.imported-badge')
    @endif
</div>
