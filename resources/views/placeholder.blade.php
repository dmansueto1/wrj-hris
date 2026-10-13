<x-layouts::app :title="__($title)">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ __($title) }}</flux:heading>

        <div class="rounded-xl border border-dashed border-brand-blue-light/50 bg-brand-tint p-8 text-center">
            <flux:text>{{ __('This page is coming soon.') }}</flux:text>
        </div>
    </div>
</x-layouts::app>
