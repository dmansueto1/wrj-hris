{{-- Present (green) or Absent (gray, the same as Clocked Out) on the All Attendance tabs (T-11.4). --}}
@if ($present)
    <x-status-badge color="green" data-test="presence-present">{{ __('Present') }}</x-status-badge>
@else
    <x-status-badge color="zinc" data-test="presence-absent">{{ __('Absent') }}</x-status-badge>
@endif
