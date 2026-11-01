{{-- Project, In, Out, Worked time, Rate, Labor cost and Photos cells of one attendance; dashes when $attendance is null (Absent). --}}
@if ($attendance)
    <flux:table.cell>
        <span class="block max-w-48 truncate">{{ $attendance->project->name }}</span>
        <span class="font-mono text-xs text-zinc-500">{{ $attendance->project->code }}</span>
    </flux:table.cell>
    <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }}</flux:table.cell>
    <flux:table.cell class="whitespace-nowrap tabular-nums">
        @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
    </flux:table.cell>
    <flux:table.cell align="end" class="tabular-nums">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance])</flux:table.cell>
    <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ \App\Support\Money::format($attendance->hourly_rate) }}</flux:table.cell>
    <flux:table.cell align="end" class="whitespace-nowrap font-medium tabular-nums text-brand-navy">
        {{ $attendance->labor_cost !== null ? \App\Support\Money::format($attendance->labor_cost) : '—' }}
    </flux:table.cell>
    <flux:table.cell>
        @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
    </flux:table.cell>
@else
    @foreach (range(1, 7) as $i)
        <flux:table.cell class="text-zinc-400">&mdash;</flux:table.cell>
    @endforeach
@endif
