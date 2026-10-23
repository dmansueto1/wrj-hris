{{-- Clock-out time, with a superscript +1, +2 … when it is on a later day than the work date (PRD 7.4 rule 6); a dash while Clocked In. --}}
@if ($attendance->clock_out_at)
    {{ $attendance->clock_out_at->format('g:i A') }}@if ($days = $attendance->clockOutDayOffset())<sup class="ms-0.5 text-[0.625rem] font-semibold text-warning" title="{{ trans_choice(':count day later|:count days later', $days, ['count' => $days]) }}" data-test="clock-out-day-offset">+{{ $days }}</sup>@endif
@else
    <span class="text-zinc-400">&mdash;</span>
@endif
