{{--
    Clock In/Out (T-10). Built for one-handed use on a phone: full-width 48px buttons and a project dropdown, like the kiosk.
    x-data="clockPad" (resources/js/app.js) gets the browser location, then calls the Livewire action.
--}}
<div class="flex flex-col gap-6" x-data="clockPad">
    <x-page-header :title="__('Clock In/Out')" :description="now()->format('l, F j, Y')" />

    @error('clock')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" role="alert" data-test="clock-error" />
    @enderror

    @if ($lastClockOut)
        <flux:callout variant="success" icon="check-circle" data-test="clock-out-summary">
            <flux:callout.heading>{{ __('Clocked out from :project', ['project' => $lastClockOut['project']]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':start to :end', ['start' => $lastClockOut['start'], 'end' => $lastClockOut['end']]) }}
                &middot;
                <span class="font-semibold tabular-nums">{{ __(':hours hours', ['hours' => $lastClockOut['hours']]) }}</span>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Hero: clock in or clock out --}}
        <section class="rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 sm:p-7">
            @if ($this->openSession)
                @php($session = $this->openSession)
                <div class="flex flex-col items-center gap-5 text-center">
                    <x-status-badge color="green">{{ __('Clocked in') }}</x-status-badge>

                    <div>
                        <p class="text-sm text-zinc-500">{{ __('Working on') }}</p>
                        <p class="text-xl font-bold text-brand-navy sm:text-2xl">{{ $session->project->name }}</p>
                        <p class="font-mono text-xs text-zinc-500">{{ $session->project->code }}</p>
                    </div>

                    <div
                        x-data="{
                            start: {{ $session->clock_in_at->getTimestamp() * 1000 }},
                            elapsed: '',
                            tick() {
                                const minutes = Math.max(0, Math.floor((Date.now() - this.start) / 60000));
                                this.elapsed = Math.floor(minutes / 60) + 'h ' + String(minutes % 60).padStart(2, '0') + 'm';
                            },
                        }"
                        x-init="tick(); setInterval(() => tick(), 60000)"
                        class="flex flex-col items-center"
                    >
                        <p class="text-5xl font-bold tabular-nums tracking-tight text-brand-navy" x-text="elapsed">&nbsp;</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('since :time', ['time' => $session->clock_in_at->format('g:i A')]) }}</p>
                    </div>

                    <flux:modal.trigger name="confirm-clock-out">
                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-danger px-5 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-danger/90 disabled:opacity-60"
                            wire:loading.attr="disabled"
                            x-bind:disabled="busy"
                            data-test="clock-out-button"
                        >
                            <flux:icon icon="stop-circle" class="size-5" />
                            {{ __('Clock Out') }}
                        </button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="confirm-clock-out" class="max-w-sm">
                    <div class="flex flex-col gap-5">
                        <div>
                            <flux:heading size="lg">{{ __('Clock out now?') }}</flux:heading>
                            <flux:text class="mt-2">{{ __('This ends your duty on :project.', ['project' => $session->project->name]) }}</flux:text>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row-reverse">
                            <button
                                type="button"
                                class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-danger px-5 font-semibold text-white transition-colors duration-150 hover:bg-danger/90 disabled:opacity-60"
                                x-on:click="clock('clockOut')"
                                x-bind:disabled="busy"
                                wire:loading.attr="disabled"
                                data-test="confirm-clock-out-button"
                            >
                                <span x-show="! busy">{{ __('Yes, clock out') }}</span>
                                <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                            </button>
                            <flux:modal.close>
                                <flux:button class="h-12 w-full">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                </flux:modal>
            @else
                <div class="flex flex-col gap-5">
                    <div>
                        <p class="text-lg font-bold text-brand-navy">{{ __('Which project are you working on?') }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Only Ongoing projects are listed.') }}</p>
                    </div>

                    @if ($this->projects->isEmpty())
                        <x-empty-state icon="building-office-2" :title="__('No Ongoing projects')" :description="__('Ask your admin to set a project to Ongoing before clocking in.')" />
                    @else
                        {{-- A dropdown handles any number of projects; on phones it opens the device's own picker. --}}
                        <flux:field>
                            <flux:label>{{ __('Project') }}</flux:label>
                            <flux:select wire:model="projectId" :placeholder="__('Choose the project you are working on…')" data-test="clock-project-select">
                                @foreach ($this->projects as $project)
                                    <flux:select.option :value="$project->id">{{ $project->code }} · {{ $project->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="projectId" />
                        </flux:field>
                    @endif

                    @if ($this->projects->isNotEmpty())
                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-gold px-5 font-semibold text-brand-navy shadow-card transition-colors duration-150 hover:bg-brand-orange hover:text-white disabled:opacity-60"
                            x-on:click="clock('clockIn')"
                            x-bind:disabled="busy"
                            wire:loading.attr="disabled"
                            data-test="clock-in-button"
                        >
                            <flux:icon icon="play-circle" class="size-5" />
                            <span x-show="! busy">{{ __('Clock In') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                        </button>
                    @endif
                </div>
            @endif
        </section>

        {{-- Today panel --}}
        <section class="rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 sm:p-6" aria-labelledby="today-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="today-heading" class="text-base font-bold text-brand-navy">{{ __('Today') }}</h2>
                <p class="text-sm text-zinc-500">
                    {{ __('Total') }}
                    <span class="ms-1 text-lg font-bold tabular-nums text-brand-navy" data-test="today-total">{{ $this->today['total_hours'] }}</span>
                    {{ __('hrs') }}
                </p>
            </div>

            @if ($this->today['sessions']->isEmpty())
                <p class="mt-4 text-sm text-zinc-500">{{ __('No duty yet today.') }}</p>
            @else
                <ol class="mt-4 flex flex-col gap-3">
                    @foreach ($this->today['sessions'] as $session)
                        <li wire:key="today-session-{{ $session->id }}" class="flex items-start gap-3 border-s-2 border-brand-gold ps-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-brand-navy">{{ $session->project->name }}</p>
                                <p class="text-sm tabular-nums text-zinc-600">
                                    {{ $session->clock_in_at->format('g:i A') }}
                                    &rarr;
                                    {{ $session->clock_out_at?->format('g:i A') ?? __('now') }}
                                </p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-brand-navy">
                                @if ($session->hours_worked !== null)
                                    {{ $session->hours_worked }} {{ __('hrs') }}
                                @else
                                    <x-status-badge color="green">{{ __('On Duty') }}</x-status-badge>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
