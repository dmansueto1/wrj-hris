{{-- Small badge with a colored dot. Color uses Flux color names so models can keep returning them. --}}
@props(['color' => 'zinc', 'dot' => true])

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

<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', $tones[$color] ?? $tones['zinc']]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
