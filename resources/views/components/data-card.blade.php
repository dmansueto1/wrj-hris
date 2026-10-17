{{-- White card around a table or card list. Shows a skeleton overlay while Livewire is loading. --}}
<div {{ $attributes->class('relative rounded-card bg-white shadow-card ring-1 ring-brand-navy/5') }}>
    {{ $slot }}

    <div wire:loading.delay class="absolute inset-0 z-10 rounded-card bg-white/70" aria-hidden="true">
        <div class="flex flex-col gap-3 p-6 motion-safe:animate-pulse">
            <div class="h-4 w-1/3 rounded bg-zinc-200"></div>
            <div class="h-4 w-full rounded bg-zinc-200"></div>
            <div class="h-4 w-5/6 rounded bg-zinc-200"></div>
            <div class="h-4 w-2/3 rounded bg-zinc-200"></div>
        </div>
    </div>
</div>
