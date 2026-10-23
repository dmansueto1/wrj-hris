{{--
    Clock In / Clock Out confirmation with the camera step (T-10.2). It can only be closed with its
    Close button (no click-outside, no Esc), which also turns the camera off. Place it inside the
    element with x-data="clockPad" so the confirm button can call clock($action).
--}}
@props([
    'name',
    'action',
    'title',
    'description' => null,
    'confirmLabel',
    'tone' => 'gold',
    'withCamera' => true,
    'confirmTest' => null,
])

<flux:modal :name="$name" :dismissible="false" :escapable="false" :closable="false" class="w-full max-w-sm">
    <div x-data="cameraCapture" class="flex flex-col gap-5" data-test="clock-photo-modal-{{ $name }}">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($description)
                <flux:text class="mt-1">{{ $description }}</flux:text>
            @endif
        </div>

        @if ($withCamera)
            <x-camera-capture />
        @endif

        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <button
                type="button"
                @class([
                    'flex h-11 flex-1 items-center justify-center gap-2 rounded-xl px-4 font-semibold shadow-card transition-colors duration-150 disabled:opacity-50',
                    'bg-brand-gold text-brand-navy hover:bg-brand-orange hover:text-white' => $tone === 'gold',
                    'bg-danger text-white hover:bg-danger/90' => $tone === 'danger',
                ])
                x-on:click="clock(@js($action))"
                x-bind:disabled="busy{{ $withCamera ? ' || ! $wire.photo' : '' }}"
                wire:loading.attr="disabled"
                @if ($confirmTest) data-test="{{ $confirmTest }}" @endif
            >
                <span x-show="! busy">{{ $confirmLabel }}</span>
                <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
            </button>
            <button
                type="button"
                class="flex h-11 flex-1 items-center justify-center rounded-xl border border-zinc-200 px-4 font-semibold text-brand-navy transition-colors duration-150 hover:bg-brand-tint"
                x-on:click="dismiss(@js($name))"
                data-test="close-{{ $name }}"
            >
                {{ __('Close') }}
            </button>
        </div>
    </div>
</flux:modal>
