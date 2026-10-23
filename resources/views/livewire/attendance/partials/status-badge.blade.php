{{-- Attendance status: Clocked In green, Clocked Out gray, Adjusted purple, Flagged red, Void gray and struck through. --}}
<x-status-badge
    :color="$attendance->statusColor()"
    :class="$attendance->status === \App\Models\Attendance::STATUS_VOID ? 'line-through' : ''"
    data-test="status-{{ $attendance->status }}"
>{{ __($attendance->statusLabel()) }}</x-status-badge>
