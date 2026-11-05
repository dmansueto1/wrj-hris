{{-- One version of an attendance in the comparison: project, In → Out, worked time, labor cost and status. --}}
<div @class([
    'rounded-xl border px-3 py-2.5 text-sm',
    'border-zinc-200 bg-zinc-50' => $archived,
    'border-brand-blue/30 bg-brand-tint' => ! $archived,
]) data-test="{{ $test }}">
    <div class="flex items-start justify-between gap-2">
        <p class="min-w-0 truncate font-semibold text-brand-navy">{{ $attendance->project->name }}</p>
        @include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])
    </div>
    <dl class="mt-1.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 tabular-nums">
        <dt class="text-zinc-500">{{ __('Clock In') }}</dt>
        <dd class="text-zinc-800">{{ $attendance->clock_in_at->format('M j, g:i A') }}</dd>
        <dt class="text-zinc-500">{{ __('Clock Out') }}</dt>
        <dd class="text-zinc-800">{{ $attendance->clock_out_at?->format('M j, g:i A') ?? '—' }}</dd>
        <dt class="text-zinc-500">{{ __('Hours') }}</dt>
        <dd class="text-zinc-800">{{ $attendance->hours_worked !== null ? $attendance->hours_worked.' h' : '—' }}</dd>
        <dt class="text-zinc-500">{{ __('Labor cost') }}</dt>
        <dd @class(['font-semibold text-brand-navy', 'line-through' => $attendance->status === \App\Models\Attendance::STATUS_VOID])>
            {{ $attendance->labor_cost !== null ? \App\Support\Money::format($attendance->labor_cost) : '—' }}
        </dd>
    </dl>
    @if ($archived)
        <p class="mt-1.5 text-xs text-zinc-500">{{ __('Archived') }}</p>
    @endif
</div>
