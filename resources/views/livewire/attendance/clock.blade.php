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
                    <x-status-badge color="green">{{ __('Clocked In') }}</x-status-badge>

                    <div>
                        <p class="text-sm text-zinc-500">{{ __('Working on') }}</p>
                        <p class="text-xl font-bold text-brand-navy sm:text-2xl">{{ $session->project->name }}</p>
                        <p class="font-mono text-xs text-zinc-500">{{ $session->project->code }}</p>
                    </div>

                    {{-- Running worked time, to the second (runningTime in resources/js/app.js). --}}
                    <div
                        x-data="runningTime({{ $session->clock_in_at->getTimestamp() * 1000 }})"
                        class="flex w-full flex-col items-center rounded-xl bg-success-soft px-4 py-5"
                        data-test="clock-running-time"
                    >
                        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-success">
                            <span class="relative flex size-2" aria-hidden="true">
                                <span class="absolute inline-flex size-full rounded-full bg-success opacity-60 motion-safe:animate-ping"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-success"></span>
                            </span>
                            {{ __('Worked time') }}
                        </p>
                        <p class="mt-2 text-4xl font-bold tabular-nums tracking-tight text-brand-navy sm:text-5xl" x-text="elapsed">{{ $session->runningTimeLabel() }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ __('Clocked In at :time', ['time' => $session->clock_in_at->format('g:i A')]) }}</p>
                    </div>

                    @if ($photoRequired)
                        <x-photo-step-note action="out" class="w-full text-start" />
                    @endif

                    {{-- Opens the confirmation; the camera step is inside it when a photo is required. --}}
                    <button
                        type="button"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-danger px-5 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-danger/90 disabled:opacity-60"
                        x-on:click="$flux.modal('confirm-clock-out').show()"
                        wire:loading.attr="disabled"
                        x-bind:disabled="busy"
                        data-test="clock-out-button"
                    >
                        <flux:icon icon="stop-circle" class="size-5" />
                        {{ __('Clock Out') }}
                    </button>
                </div>

                <x-clock-photo-modal
                    name="confirm-clock-out"
                    action="clockOut"
                    tone="danger"
                    :title="__('Clock out now?')"
                    :description="$photoRequired
                        ? __('Take a photo of yourself. This ends your attendance on :project.', ['project' => $session->project->name])
                        : __('This ends your attendance on :project.', ['project' => $session->project->name])"
                    :confirm-label="__('Yes, clock out')"
                    :with-camera="$photoRequired"
                    confirm-test="confirm-clock-out-button"
                />
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
                            <flux:select wire:model="projectId" :placeholder="__('Select project')" data-test="clock-project-select">
                                @foreach ($this->projects as $project)
                                    <flux:select.option :value="$project->id">{{ $project->code }} · {{ $project->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="projectId" />
                        </flux:field>
                    @endif

                    @if ($this->projects->isNotEmpty())
                        @if ($photoRequired)
                            <x-photo-step-note action="in" />
                        @endif

                        {{-- Enabled once a project is chosen. With a photo required it opens the camera modal. --}}
                        <button
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-gold px-5 font-semibold text-brand-navy shadow-card transition-colors duration-150 hover:bg-brand-orange hover:text-white disabled:opacity-60"
                            x-on:click="@js($photoRequired) ? $flux.modal('clock-in-photo').show() : clock('clockIn')"
                            x-bind:disabled="busy || ! $wire.projectId"
                            wire:loading.attr="disabled"
                            data-test="clock-in-button"
                        >
                            <flux:icon icon="play-circle" class="size-5" />
                            <span x-show="! busy">{{ __('Clock In') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                        </button>

                        @if ($photoRequired)
                            <x-clock-photo-modal
                                name="clock-in-photo"
                                action="clockIn"
                                :title="__('Take your photo')"
                                :description="__('A photo of you is saved with your clock in.')"
                                :confirm-label="__('Clock In')"
                                confirm-test="confirm-clock-in-button"
                            />
                        @endif
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
                    h
                </p>
            </div>

            @if ($this->today['sessions']->isEmpty())
                <p class="mt-4 text-sm text-zinc-500">{{ __('No attendance yet today.') }}</p>
            @else
                <ol class="mt-4 flex flex-col gap-3">
                    @foreach ($this->today['sessions'] as $session)
                        <li wire:key="today-session-{{ $session->id }}" class="flex items-start gap-3 rounded-xl border-s-2 border-brand-gold bg-brand-page/60 px-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-brand-navy">{{ $session->project->name }}</p>
                                <p class="text-sm tabular-nums text-zinc-600">
                                    {{ $session->clock_in_at->format('g:i A') }}
                                    &rarr;
                                    @if ($session->clock_out_at)
                                        @include('livewire.attendance.partials.clock-out-time', ['attendance' => $session])
                                    @else
                                        {{ __('now') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                {{-- The running time is on the card on the left; here a Clocked In record shows only its status. --}}
                                @if ($session->clock_out_at)
                                    <span class="text-sm font-semibold tabular-nums text-brand-navy">
                                        @include('livewire.attendance.partials.worked-time', ['attendance' => $session])
                                    </span>
                                @endif
                                @include('livewire.attendance.partials.status-badge', ['attendance' => $session])
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
