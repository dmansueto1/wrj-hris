{{-- Summary number above a list: icon in a tinted circle, label, value and a short note. --}}
@props(['label', 'value', 'icon', 'color' => 'blue', 'note' => null])

@php
    $tones = [
        'green' => 'bg-success-soft text-success',
        'amber' => 'bg-warning-soft text-warning',
        'red' => 'bg-danger-soft text-danger',
        'blue' => 'bg-info-soft text-info',
        'indigo' => 'bg-indigo-soft text-indigo',
        'zinc' => 'bg-neutral-soft text-neutral',
    ];
@endphp

<div {{ $attributes->class('flex items-start gap-4 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 transition-shadow duration-200 hover:shadow-card-hover') }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $tones[$color] ?? $tones['blue'] }}">
        <flux:icon :icon="$icon" class="size-5" />
    </span>
    <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-brand-navy">{{ $value }}</p>
        @if ($note)
            <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $note }}</p>
        @endif
    </div>
</div>
