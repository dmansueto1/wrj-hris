{{-- Initials in a colored circle. The color is picked from the name, so a person always gets the same color. --}}
@props(['name', 'initials' => null, 'size' => 'md'])

@php
    $palette = [
        'bg-amber-100 text-amber-800',
        'bg-sky-100 text-sky-800',
        'bg-emerald-100 text-emerald-800',
        'bg-indigo-100 text-indigo-800',
        'bg-rose-100 text-rose-800',
        'bg-teal-100 text-teal-800',
    ];
    $color = $palette[crc32((string) $name) % count($palette)];
    $initials ??= \Illuminate\Support\Str::of((string) $name)->explode(' ')->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('');
    $sizes = ['sm' => 'size-7 text-[0.6875rem]', 'md' => 'size-9 text-xs', 'lg' => 'size-11 text-sm'];
@endphp

<span {{ $attributes->class(["inline-flex shrink-0 items-center justify-center rounded-full font-semibold uppercase", $color, $sizes[$size] ?? $sizes['md']]) }} aria-hidden="true">{{ $initials }}</span>
