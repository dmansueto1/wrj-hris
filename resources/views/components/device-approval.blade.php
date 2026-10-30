{{--
    Shown instead of the kiosk or the Clock In/Out card on a device that is not approved (T-11.1).
    state: new (Request access), pending (Request sent + code), blocked. The parent component has
    the requestAccess() action.
--}}
@props(['state', 'code' => null, 'requestedAt' => null])

@php
    $look = [
        'new' => ['icon' => 'shield-check', 'tone' => 'bg-warning-soft text-brand-gold'],
        'pending' => ['icon' => 'clock', 'tone' => 'bg-info-soft text-info'],
        'blocked' => ['icon' => 'no-symbol', 'tone' => 'bg-danger-soft text-danger'],
    ][$state] ?? ['icon' => 'shield-check', 'tone' => 'bg-warning-soft text-brand-gold'];
@endphp

<section {{ $attributes->class('mx-auto w-full max-w-lg rounded-card bg-white p-6 text-center shadow-card-hover ring-1 ring-brand-navy/5 sm:p-8') }} data-test="device-approval-{{ $state }}">
    <span class="mx-auto flex size-16 items-center justify-center rounded-full {{ $look['tone'] }}">
        <flux:icon :icon="$look['icon']" class="size-8" />
    </span>

    @if ($state === 'blocked')
        <h2 class="mt-5 text-xl font-bold text-brand-navy">{{ __('This device is blocked') }}</h2>
        <p class="mt-2 text-sm text-zinc-600">{{ __('Ask your admin if you think this is a mistake.') }}</p>
    @elseif ($state === 'pending')
        <h2 class="mt-5 text-xl font-bold text-brand-navy">{{ __('Request sent') }}</h2>
        <p class="mt-2 text-sm text-zinc-600">{{ __('Your admin needs to approve this device. Show them this code:') }}</p>
    @else
        <h2 class="mt-5 text-xl font-bold text-brand-navy">{{ __('This device needs approval') }}</h2>
        <p class="mt-2 text-sm text-zinc-600">
            {{ __('To keep attendance accurate, only devices approved by your admin can use this page. Tap Request access to send a request.') }}
        </p>
    @endif

    @if ($code && $state !== 'new')
        <p class="mt-5 inline-block rounded-xl bg-brand-page px-5 py-3 font-mono text-3xl font-bold tracking-[0.2em] text-brand-navy" data-test="device-code">{{ $code }}</p>
    @endif

    @if ($state === 'pending' && $requestedAt)
        <p class="mt-3 text-xs text-zinc-500">{{ __('Requested :time', ['time' => $requestedAt->format('M j, g:i A')]) }}</p>
    @endif

    @error('device')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" class="mt-5 text-start" />
    @enderror

    <div class="mt-6 flex flex-col items-center gap-3">
        @if ($state === 'new')
            <button
                type="button"
                wire:click="requestAccess"
                wire:loading.attr="disabled"
                class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-gold px-5 font-semibold text-brand-navy shadow-card transition-colors duration-150 hover:bg-brand-orange hover:text-white disabled:opacity-60 sm:w-64"
                data-test="request-access-button"
            >
                <span wire:loading.remove wire:target="requestAccess" class="flex items-center gap-2">
                    <flux:icon icon="paper-airplane" class="size-5" /> {{ __('Request access') }}
                </span>
                <span wire:loading wire:target="requestAccess">{{ __('Sending…') }}</span>
            </button>
            <p class="text-xs text-zinc-500">{{ __("Your admin will see this device's browser and type, not your personal data.") }}</p>
        @elseif ($state === 'pending')
            <flux:button wire:click="$refresh" icon="arrow-path" data-test="check-again-button">{{ __('Check again') }}</flux:button>
        @endif
    </div>
</section>
