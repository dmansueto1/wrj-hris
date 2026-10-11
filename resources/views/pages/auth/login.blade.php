<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Sign in')" :description="__('')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
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
                    type="text"
                    required
                    autofocus
                    autocapitalize="none"
                    autocomplete="username"
                    placeholder="Username"
                />
            </flux:field>

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <!-- Remember Me / Forgot password -->
            <div class="flex items-center justify-between gap-4">
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm whitespace-nowrap" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

    </div>
</x-layouts::auth>
