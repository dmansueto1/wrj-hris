{{-- An active filter that can be removed with one click. Pass the wire:click that clears it. --}}
@props(['label'])

<button type="button" {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-brand-tint px-2.5 py-1 text-xs font-medium text-brand-blue-dark transition-colors duration-150 hover:bg-brand-blue hover:text-white') }}>
    {{ $label }}
    <flux:icon icon="x-mark" class="size-3.5" />
    <span class="sr-only">{{ __('Remove filter') }}</span>
</button>
