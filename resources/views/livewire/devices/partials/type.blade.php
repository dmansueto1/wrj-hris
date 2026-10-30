{{-- Device type from the browser: Phone, Tablet or Computer (laptops and desktops look the same). --}}
<div class="flex items-center gap-2 whitespace-nowrap" data-test="device-type-{{ $device->id }}">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand-blue">
        <flux:icon :icon="$device->typeIcon()" class="size-4" />
    </span>
    <span class="text-sm font-medium text-brand-navy">{{ __($device->typeLabel()) }}</span>
</div>
