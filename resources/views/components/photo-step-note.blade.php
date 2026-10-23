{{-- Shown above Clock In / Clock Out when a photo is required (T-10.2): tells the worker the next step is the camera. --}}
@props(['action' => 'in'])

<div {{ $attributes->class('flex items-start gap-3 rounded-xl bg-info-soft px-4 py-3 text-sm text-brand-navy') }} role="note" data-test="photo-step-note">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-info shadow-card">
        <flux:icon icon="camera" class="size-4" />
    </span>
    <p>
        <span class="font-semibold">{{ __('Camera required.') }}</span>
        {{ $action === 'out'
            ? __('After you press Clock Out, take a photo of yourself to confirm.')
            : __('After you press Clock In, take a photo of yourself to confirm.') }}
    </p>
</div>
