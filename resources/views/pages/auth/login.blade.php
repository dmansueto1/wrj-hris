<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight text-brand-navy">{{ __('Sign in') }}</h1>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Login error, shown above the fields -->
            @error('username')
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" />
            @enderror

            <!-- Username: built from field parts so Flux does not repeat the error below the input -->
            <flux:field>
                <flux:label>{{ __('Username') }}</flux:label>
                <flux:input
                    name="username"
                    :value="old('username')"
                    icon="user"
                    type="text"
                    required
                    autofocus
                    autocapitalize="none"
                    autocomplete="username"
                    :placeholder="__('e.g. jdoe')"
                />
            </flux:field>

            <!-- Password -->
            <flux:field>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:input
                    name="password"
                    icon="lock-closed"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
            </flux:field>

            <!-- Remember Me / Forgot password -->
            <div class="flex items-center justify-between gap-4">
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm whitespace-nowrap" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <button
                type="submit"
                class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-blue px-5 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-brand-blue-dark"
                data-test="login-button"
            >
                {{ __('Log in') }}
                <flux:icon icon="arrow-right" class="size-4" />
            </button>
        </form>

        <!-- Workers on site clock in at the shared kiosk without logging in -->
        <a
            href="{{ route('kiosk') }}"
            class="group flex items-center gap-3 rounded-xl border border-dashed border-brand-gold/60 bg-warning-soft/60 px-4 py-3 text-sm transition-colors duration-150 hover:bg-warning-soft"
            data-test="kiosk-link"
        >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-gold text-brand-navy">
                <flux:icon icon="clock" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-brand-navy">{{ __('Clocking in on site?') }}</span>
                <span class="block text-xs text-zinc-600">{{ __('Use the attendance kiosk, no login needed.') }}</span>
            </span>
            <flux:icon icon="arrow-right" class="size-4 text-brand-navy transition-transform duration-150 group-hover:translate-x-0.5" />
        </a>
    </div>
</x-layouts::auth>
