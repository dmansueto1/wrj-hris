{{--
    Kiosk (T-10.1). Step 1: username + password. Step 2: Clock In (pick a project) or Clock Out.
    x-data="clockPad" (resources/js/app.js) gets the browser location, then calls the Livewire action.
--}}
<div class="grid gap-6 lg:h-[32rem] lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]" x-data="clockPad">
    <section class="flex flex-col overflow-hidden rounded-card bg-white shadow-card-hover ring-1 ring-brand-navy/5 lg:h-full">
        {{-- Step indicator --}}
        @php($step = $this->user === null ? 1 : 2)
        <ol class="flex shrink-0 border-b border-zinc-100 text-xs font-semibold uppercase tracking-wider" aria-label="{{ __('Steps') }}">
            @foreach ([1 => __('Sign in'), 2 => __('Clock in or out')] as $number => $label)
                <li @class([
                    'flex flex-1 items-center gap-2 px-5 py-3 transition-colors duration-200',
                    'bg-warning-soft text-brand-navy' => $step === $number,
                    'text-zinc-400' => $step !== $number,
                ]) @if ($step === $number) aria-current="step" @endif>
                    <span @class([
                        'flex size-5 items-center justify-center rounded-full text-[0.6875rem]',
                        'bg-brand-gold text-brand-navy' => $step >= $number,
                        'bg-zinc-200 text-zinc-500' => $step < $number,
                    ])>{{ $number }}</span>
                    {{ $label }}
                </li>
            @endforeach
        </ol>

        <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-7">
            @error('kiosk')
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" class="mb-5" data-test="kiosk-error" />
            @enderror

            @if ($this->user === null)
                {{-- Step 1: sign in --}}
                <form wire:submit="verify" class="flex flex-col gap-5">
                    <div>
                        <h1 class="text-xl font-bold text-brand-navy">{{ __('Clock in or out') }}</h1>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Enter your username and password to continue.') }}</p>
                    </div>

                    @error('username')
                        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" data-test="kiosk-credentials-error" />
                    @enderror

                    <flux:field>
                        <flux:label>{{ __('Username') }}</flux:label>
                        <flux:input
                            wire:model="username"
                            icon="user"
                            type="text"
                            autocapitalize="none"
                            autocomplete="off"
                            spellcheck="false"
                            :placeholder="__('e.g. jdoe')"
                            required
                            autofocus
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input wire:model="password" icon="lock-closed" type="password" autocomplete="off" required viewable />
                    </flux:field>

                    <button
                        type="submit"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-blue px-5 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-brand-blue-dark disabled:opacity-60"
                        wire:loading.attr="disabled"
                        data-test="kiosk-continue-button"
                    >
                        <span wire:loading.remove wire:target="verify" class="flex items-center gap-2">
                            {{ __('Continue') }} <flux:icon icon="arrow-right" class="size-4" />
                        </span>
                        <span wire:loading wire:target="verify">{{ __('Checking…') }}</span>
                    </button>
                </form>
            @else
                {{-- Step 2: clock in or out --}}
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :name="$displayName" :initials="$this->user->initials()" size="lg" />
                            <div class="min-w-0">
                                <p class="truncate text-xl font-bold text-brand-navy">{{ __('Hi, :name', ['name' => $displayName]) }}</p>
                                <p class="text-sm text-zinc-500">{{ $this->openDuty ? __('Ready to clock out?') : __('Ready to start work?') }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="startOver" class="shrink-0 text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline" data-test="kiosk-start-over">
                            {{ __('Not you? Start over') }}
                        </button>
                    </div>

                    @if ($this->openDuty)
                        @php($duty = $this->openDuty)
                        <div
                            class="rounded-xl bg-success-soft p-5"
                            x-data="{
                                start: {{ $duty->clock_in_at->getTimestamp() * 1000 }},
                                elapsed: '',
                                tick() {
                                    const minutes = Math.max(0, Math.floor((Date.now() - this.start) / 60000));
                                    this.elapsed = Math.floor(minutes / 60) + 'h ' + String(minutes % 60).padStart(2, '0') + 'm';
                                },
                            }"
                            x-init="tick(); setInterval(() => tick(), 30000)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-success">
                                    <span class="relative flex size-2.5">
                                        <span class="absolute inline-flex size-full rounded-full bg-success opacity-60 motion-safe:animate-ping"></span>
                                        <span class="relative inline-flex size-2.5 rounded-full bg-success"></span>
                                    </span>
                                    {{ __('Clocked In') }}
                                </span>
                                <span class="text-2xl font-bold tabular-nums text-brand-navy" x-text="elapsed">&nbsp;</span>
                            </div>
                            <p class="mt-3 font-semibold text-brand-navy">{{ $duty->project->name }}</p>
                            <p class="text-sm text-zinc-600">{{ __('Clocked In since :time', ['time' => $duty->clock_in_at->format('g:i A')]) }}</p>
                        </div>

                        @if ($photoRequired)
                            <x-photo-step-note action="out" />
                        @endif

                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-danger px-5 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-danger/90 disabled:opacity-60"
                            x-on:click="@js($photoRequired) ? $flux.modal('kiosk-photo-out').show() : clock('clockOut')"
                            x-bind:disabled="busy"
                            wire:loading.attr="disabled"
                            data-test="kiosk-clock-out-button"
                        >
                            <flux:icon icon="stop-circle" class="size-5" />
                            <span x-show="! busy">{{ __('Clock Out') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                        </button>

                        @if ($photoRequired)
                            <x-clock-photo-modal
                                name="kiosk-photo-out"
                                action="clockOut"
                                tone="danger"
                                :title="__('Take your photo')"
                                :description="__('A photo of you is saved with your clock out.')"
                                :confirm-label="__('Clock Out')"
                                confirm-test="kiosk-confirm-clock-out-button"
                            />
                        @endif
                    @elseif ($this->projects->isEmpty())
                        <x-empty-state icon="building-office-2" :title="__('No Ongoing projects')" :description="__('Ask your admin to set a project to Ongoing before clocking in.')" />
                    @else
                        <flux:field>
                            <flux:label>{{ __('Project') }}</flux:label>
                            <flux:select wire:model="projectId" :placeholder="__('Select project')" data-test="kiosk-project-select">
                                @foreach ($this->projects as $project)
                                    <flux:select.option :value="$project->id">{{ $project->code }} · {{ $project->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="projectId" />
                        </flux:field>

                        @if ($photoRequired)
                            <x-photo-step-note action="in" />
                        @endif

                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-gold px-5 font-semibold text-brand-navy shadow-card transition-colors duration-150 hover:bg-brand-orange hover:text-white disabled:opacity-60"
                            x-on:click="@js($photoRequired) ? $flux.modal('kiosk-photo-in').show() : clock('clockIn')"
                            x-bind:disabled="busy || ! $wire.projectId"
                            wire:loading.attr="disabled"
                            data-test="kiosk-clock-in-button"
                        >
                            <flux:icon icon="play-circle" class="size-5" />
                            <span x-show="! busy">{{ __('Clock In') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                        </button>

                        @if ($photoRequired)
                            <x-clock-photo-modal
                                name="kiosk-photo-in"
                                action="clockIn"
                                :title="__('Take your photo')"
                                :description="__('A photo of you is saved with your clock in.')"
                                :confirm-label="__('Clock In')"
                                confirm-test="kiosk-confirm-clock-in-button"
                            />
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Same fixed height as the sign-in card on wide screens; the list scrolls inside. --}}
    <livewire:attendance.kiosk-duties />
</div>
