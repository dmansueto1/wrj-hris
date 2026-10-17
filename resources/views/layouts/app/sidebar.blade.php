<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-brand-page text-brand-text antialiased">
        <flux:sidebar sticky collapsible class="dark border-e border-brand-navy bg-brand-navy">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('clock') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                {{-- Only the items the user has permission for, grouped by section (App\Support\Navigation). --}}
                @php($currentMenuRoute = \App\Support\Navigation::owner(request()->route()?->getName())['route'] ?? null)
                @foreach (\App\Support\Navigation::groupedFor(auth()->user()) as $group => $items)
                    <flux:sidebar.group :heading="__($group)" class="grid">
                        @foreach ($items as $item)
                            <flux:sidebar.item
                                :icon="$item['icon']"
                                :href="route($item['route'])"
                                :current="$item['route'] === $currentMenuRoute"
                                :tooltip="__($item['label'])"
                                data-test="nav-{{ $item['route'] }}"
                                wire:navigate
                            >
                                {{ __($item['label']) }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        {{-- Top bar: breadcrumbs, today's date and a live clock, quick Clock In/Out. --}}
        <flux:header sticky class="z-20 border-b border-zinc-200 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/75">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('Open menu')" />

            <nav aria-label="{{ __('Breadcrumb') }}" class="min-w-0 max-lg:ms-2">
                <ol class="flex items-center gap-1.5 truncate text-sm">
                    @foreach (\App\Support\Navigation::breadcrumbs(request()->route()?->getName(), $title ?? null) as $crumb)
                        <li class="flex items-center gap-1.5 truncate @if (! $loop->last) max-sm:hidden @endif">
                            @if (! $loop->first)
                                <flux:icon icon="chevron-right" class="size-3.5 text-zinc-400" />
                            @endif
                            @if ($crumb['url'] && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" wire:navigate class="text-zinc-500 transition-colors duration-150 hover:text-brand-blue">{{ __($crumb['label']) }}</a>
                            @else
                                <span @class(['truncate', 'font-semibold text-brand-navy' => $loop->last, 'text-zinc-500' => ! $loop->last])>{{ __($crumb['label']) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            <flux:spacer />

            <div
                class="me-3 hidden items-center gap-2 text-sm text-zinc-600 md:flex"
                x-data="{
                    time: '',
                    tick() {
                        this.time = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date());
                    },
                }"
                x-init="tick(); setInterval(() => tick(), 1000)"
            >
                <flux:icon icon="calendar-days" class="size-4 text-zinc-400" />
                <span>{{ now()->format('D, M j, Y') }}</span>
                <span class="text-zinc-300" aria-hidden="true">|</span>
                <span class="font-semibold tabular-nums text-brand-navy" x-text="time" aria-live="off">{{ now()->format('g:i:s A') }}</span>
            </div>

            @can('attendance.clock')
                <flux:button size="sm" icon="clock" :href="route('clock')" wire:navigate class="btn-accent" data-test="topbar-clock-button">
                    <span class="max-sm:sr-only">{{ __('Clock In/Out') }}</span>
                </flux:button>
            @endcan

            <flux:dropdown position="bottom" align="end" class="ms-2 lg:hidden">
                <button type="button" class="rounded-full" aria-label="{{ __('Open user menu') }}">
                    <x-avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                </button>

                <flux:menu>
                    <x-user-menu-details />
                    <flux:menu.separator />
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
