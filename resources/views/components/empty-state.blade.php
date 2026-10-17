{{-- Shown instead of blank space when a list has no rows. --}}
@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-6 py-12 text-center') }}>
    <span class="flex size-14 items-center justify-center rounded-full bg-warning-soft text-brand-gold">
        <flux:icon :icon="$icon" class="size-7" />
    </span>
    <div>
        <p class="font-semibold text-brand-navy">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $description }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
