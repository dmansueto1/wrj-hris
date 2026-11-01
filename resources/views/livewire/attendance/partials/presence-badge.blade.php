{{-- Present (green) or Absent (gray, the same as Clocked Out) on the attendance pages (T-11.4); a dash for a day that has not happened yet. --}}
@if (! empty($future))
    <span class="text-zinc-400" title="{{ __('Not yet') }}" data-test="presence-none">&mdash;</span>
@elseif ($present)
    <x-status-badge color="green" data-test="presence-present">{{ __('Present') }}</x-status-badge>
@else
    <x-status-badge color="zinc" data-test="presence-absent">{{ __('Absent') }}</x-status-badge>
@endif
