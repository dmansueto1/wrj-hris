{{-- Present (green), Rest Day (blue: no attendance on a rest day of the employee's shift) or Absent (gray, the same as Clocked Out) on the attendance pages (T-11.4); a dash for a day that has not happened yet. --}}
@if ($present)
    <x-status-badge color="green" data-test="presence-present">{{ __('Present') }}</x-status-badge>
@elseif (! empty($restDay))
    <x-status-badge color="blue" data-test="presence-rest-day">{{ __('Rest Day') }}</x-status-badge>
@elseif (! empty($future))
    <span class="text-zinc-400" title="{{ __('Not yet') }}" data-test="presence-none">&mdash;</span>
@else
    <x-status-badge color="zinc" data-test="presence-absent">{{ __('Absent') }}</x-status-badge>
@endif
