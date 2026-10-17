{{-- Every page starts with this: bold title, one-line description, actions on the right. --}}
@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])

<header {{ $attributes->class('relative overflow-hidden rounded-card bg-blueprint px-5 py-6 shadow-card ring-1 ring-brand-navy/5 sm:px-7') }}>
    @if ($back)
        <a href="{{ $back }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-brand-blue hover:text-brand-blue-dark">
            <flux:icon icon="arrow-left" class="size-4" /> {{ $backLabel ?? __('Back') }}
        </a>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">{{ $title }}</h1>
                {{ $badge ?? '' }}
            </div>
            @if ($description)
                <p class="mt-1 text-sm text-zinc-600">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</header>
