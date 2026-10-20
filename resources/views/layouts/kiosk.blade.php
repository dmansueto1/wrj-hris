{{-- Full-screen layout for the shared site kiosk (T-10.1): navy hero band, live clock, no sidebar or user menu. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-brand-page text-brand-text antialiased">
        <div class="relative">
            {{-- Hero band, like the login page --}}
            <div class="bg-hero-blueprint absolute inset-x-0 top-0 h-80" aria-hidden="true">
                <div class="absolute inset-x-0 bottom-0 h-1 bg-brand-gold"></div>
            </div>

            <div class="relative mx-auto max-w-6xl px-4 pb-10 sm:px-6">
                <header
                    class="flex flex-wrap items-center justify-between gap-6 py-6 text-white"
                    x-data="{
                        time: '',
                        greeting: '',
                        tick() {
                            const now = new Date();
                            this.time = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' }).format(now);
                            const hour = Number(new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', hourCycle: 'h23' }).format(now));
                            this.greeting = hour < 12 ? @js(__('Good morning')) : (hour < 18 ? @js(__('Good afternoon')) : @js(__('Good evening')));
                        },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                >
                    <div class="flex items-center gap-3">
                        <span class="overflow-hidden rounded-xl shadow-lg ring-1 ring-white/10">
                            <x-app-logo-icon class="size-12" />
                        </span>
                        <div>
                            <p class="text-lg font-bold leading-tight">WRJ88 Construction</p>
                            <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-brand-blue-light">
                                {{ __('Attendance Kiosk') }}
                            </p>
                        </div>
                    </div>

                    <div class="text-end">
                        <p class="text-sm font-medium text-brand-gold" x-text="greeting">&nbsp;</p>
                        <p class="text-4xl font-bold tabular-nums tracking-tight sm:text-5xl" x-text="time">{{ now()->format('g:i A') }}</p>
                        <p class="text-sm text-white/70">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                </header>

                <main class="mt-2">
                    {{ $slot }}
                </main>

                <p class="mt-8 text-center text-xs text-zinc-500">
                    {{ __('Forgot your password? Ask your admin.') }} &middot; &copy; {{ now()->year }} WRJ88 Construction Corporation
                </p>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
