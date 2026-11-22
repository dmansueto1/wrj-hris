{{-- A day's Late (T-16.1): an amber badge with hours like Worked time ("0.42 h") against the shift saved at clock-in, or a dash. $day: App\Support\AttendanceDay. --}}
@php($minutes = $day->lateMinutes())

@if ($minutes > 0)
    @php($text = __('Late :minutes min (shift :shift)', ['minutes' => $minutes, 'shift' => $day->shiftLabel()]))
    @include('livewire.attendance.partials.late-badge', ['hours' => \App\Support\Costing\LaborCost::hoursForMinutes($minutes), 'title' => $text, 'test' => 'late-hours'])
    <span class="sr-only">{{ $text }}</span>
@else
    <span class="text-zinc-400">&mdash;</span>
@endif
