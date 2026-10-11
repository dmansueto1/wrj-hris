@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <img src="{{ asset('images/wrj88-logo.png') }}" alt="WRJ88 Construction Corporation" class="size-8 object-cover" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <img src="{{ asset('images/wrj88-logo.png') }}" alt="WRJ88 Construction Corporation" class="size-8 object-cover" />
        </x-slot>
    </flux:brand>
@endif
