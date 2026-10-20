{{-- Sign-in pages (login, forgot / reset / change password): blueprint hero band, live greeting and clock, floating card. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-brand-page text-brand-text antialiased">
        <div class="relative flex min-h-svh flex-col items-center px-4 py-10 sm:justify-center">
            {{-- Navy hero band with a blueprint grid and a thin gold line --}}
            <div class="bg-hero-blueprint absolute inset-x-0 top-0 h-96" aria-hidden="true">
                <div class="absolute inset-x-0 bottom-0 h-1 bg-brand-gold"></div>
            </div>

            <div class="relative w-full max-w-sm">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 text-center" wire:navigate>
                    <span class="overflow-hidden rounded-2xl shadow-lg ring-1 ring-white/10">
                        <x-app-logo-icon class="size-20" />
                    </span>
                    <span>
                        <span class="block text-lg font-semibold tracking-wide text-white">WRJ88 Construction Corporation</span>
                        <span class="block text-xs font-medium uppercase tracking-[0.2em] text-brand-blue-light">{{ __('Employee Portal') }}</span>
                    </span>
                </a>

                <div class="mt-6 rounded-card bg-white p-6 shadow-card-hover ring-1 ring-brand-navy/5 sm:p-8">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

                <p class="mt-6 text-center text-xs text-brand-text/60">
                    &copy; {{ now()->year }} WRJ88 Construction Corporation. All rights reserved.
                </p>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
