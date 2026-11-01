{{-- One attendance on a phone card: project, In → Out, worked time × rate = labor cost, photos. --}}
<div class="rounded-xl border-s-2 border-brand-gold bg-brand-page/60 px-3 py-2">
    <p class="truncate text-sm font-semibold text-brand-navy">{{ $attendance->project->name }}</p>
    <p class="text-sm tabular-nums text-zinc-700">
        {{ $attendance->clock_in_at->format('g:i A') }} &rarr;
        @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
    </p>
    <div class="mt-1 flex items-center justify-between gap-2">
        @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
        <p class="text-end text-sm tabular-nums">
            <span class="text-zinc-500">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance]) &times; {{ \App\Support\Money::format($attendance->hourly_rate) }}</span>
            <span class="block font-semibold text-brand-navy">{{ $attendance->labor_cost !== null ? \App\Support\Money::format($attendance->labor_cost) : '—' }}</span>
        </p>
    </div>
</div>
