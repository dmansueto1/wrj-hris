{{-- "Your shift today: …" under the project picker on Clock In/Out and the kiosk (T-11.3). Nothing when $shift is null (Flexible). --}}
@props(['shift' => null])

@if ($shift)
    <p {{ $attributes->class('flex items-center gap-2 text-sm text-zinc-600') }} data-test="shift-today">
        <flux:icon icon="calendar-days" class="size-4 shrink-0 text-brand-blue" />
        {{ __('Your shift today: :shift', ['shift' => $shift]) }}
    </p>
@endif
