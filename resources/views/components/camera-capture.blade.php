{{--
    Camera preview and buttons for the photo step (T-10.2). Must sit inside an element with
    x-data="cameraCapture" (resources/js/app.js) — <x-clock-photo-modal> provides it.
--}}
<div {{ $attributes->class('flex flex-col gap-3') }} data-test="camera-capture">
    <div class="relative mx-auto aspect-[3/4] w-full max-w-60 overflow-hidden rounded-xl bg-brand-navy ring-1 ring-brand-navy/10">
        {{-- Camera off until the user asks for it --}}
        <div x-show="state === 'idle'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center text-white/80">
            <flux:icon icon="camera" class="size-10 text-brand-gold" />
            <p class="text-sm">{{ __('A photo of you is needed to clock in or out.') }}</p>
        </div>

        <video x-ref="video" x-show="state === 'live' || state === 'starting'" x-cloak class="size-full -scale-x-100 object-cover" playsinline muted></video>
        <img x-show="preview" x-bind:src="preview" alt="{{ __('Your photo') }}" class="size-full -scale-x-100 object-cover" x-cloak />

        <div x-show="state === 'starting'" x-cloak class="absolute inset-0 flex items-center justify-center text-sm text-white/80">{{ __('Starting camera…') }}</div>
        <div x-show="state === 'uploading'" x-cloak class="absolute inset-x-0 bottom-0 bg-brand-navy/80 py-2 text-center text-xs font-medium text-white">{{ __('Saving photo…') }}</div>
        <div x-show="state === 'taken'" x-cloak class="absolute end-2 top-2 rounded-full bg-success px-2 py-0.5 text-xs font-semibold text-white">{{ __('Photo ready') }}</div>

        <div x-show="state === 'error'" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-danger-soft p-4 text-center text-sm font-medium text-danger">
            <flux:icon icon="video-camera-slash" class="size-8" />
            {{ __('Allow camera access to clock in or out. Ask your admin if this device has no camera.') }}
        </div>
    </div>

    <div class="flex justify-center gap-2">
        <button type="button" x-show="state === 'idle'" x-on:click="start()" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-navy px-4 text-sm font-semibold text-white transition-colors duration-150 hover:bg-brand-blue-dark" data-test="open-camera-button">
            <flux:icon icon="video-camera" class="size-4" /> {{ __('Open camera') }}
        </button>
        <button type="button" x-show="state === 'live'" x-cloak x-on:click="take()" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-navy px-4 text-sm font-semibold text-white transition-colors duration-150 hover:bg-brand-blue-dark" data-test="take-photo-button">
            <flux:icon icon="camera" class="size-4" /> {{ __('Take photo') }}
        </button>
        <button type="button" x-show="state === 'taken' || state === 'error'" x-cloak x-on:click="retake()" class="inline-flex h-10 items-center gap-2 rounded-xl border border-zinc-200 px-4 text-sm font-semibold text-brand-navy transition-colors duration-150 hover:bg-brand-tint">
            <flux:icon icon="arrow-path" class="size-4" /> <span x-text="state === 'error' ? @js(__('Try again')) : @js(__('Retake'))"></span>
        </button>
    </div>

    @error('photo')
        <p class="text-center text-sm font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
