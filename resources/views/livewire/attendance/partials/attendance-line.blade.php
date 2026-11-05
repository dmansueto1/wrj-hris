{{-- One attendance on a phone card: project, In → Out, worked time and photos (no peso amounts). --}}
<div class="rounded-xl border-s-2 border-brand-gold bg-brand-page/60 px-3 py-2">
    <div class="flex items-center justify-between gap-2">
        <p class="truncate text-sm font-semibold text-brand-navy">{{ $attendance->project->name }}</p>
        @if ($attendance->isImported())
            @include('livewire.attendance.partials.imported-badge')
        @endif
    </div>
    <div class="mt-0.5 flex items-center justify-between gap-2 text-sm">
        <span class="tabular-nums text-zinc-700">
            {{ $attendance->clock_in_at->format('g:i A') }} &rarr;
            @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
        </span>
        <span class="font-semibold tabular-nums text-brand-navy">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance])</span>
    </div>
    @if (($photos ?? true) && ($attendance->clock_in_photo_path || $attendance->clock_out_photo_path))
        <div class="mt-1.5">
            @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
        </div>
    @endif
</div>
