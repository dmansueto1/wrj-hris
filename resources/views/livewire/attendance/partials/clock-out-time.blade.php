{{--
    Clock-out time, with a superscript +1, +2 … when it is on a later day than the work date (PRD 7.4 rule 6).
    Still Clocked In: "now" in green, like the running worked time. Otherwise (e.g. flagged) a dash.
--}}
@if ($attendance->clock_out_at)
    {{ $attendance->clock_out_at->format('g:i A') }}@if ($days = $attendance->clockOutDayOffset())<sup class="ms-0.5 text-[0.625rem] font-semibold text-warning" title="{{ trans_choice(':count day later|:count days later', $days, ['count' => $days]) }}" data-test="clock-out-day-offset">+{{ $days }}</sup>@endif
@elseif ($attendance->status === \App\Models\Attendance::STATUS_OPEN)
    <span class="font-semibold text-success" title="{{ __('Still Clocked In') }}" data-test="clock-out-now">{{ __('Still in') }}</span>
@else
    <span class="text-zinc-400">&mdash;</span>
@endif
