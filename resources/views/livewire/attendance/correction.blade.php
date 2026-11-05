{{-- Correct attendance modal (T-12), Admin only: set clock-out, change times, change project, split or void, with a reason. --}}
<div>
    <flux:modal name="attendance-correction" class="w-full max-w-lg">
        @if ($attendance = $this->attendance)
            <form wire:submit="save" class="flex flex-col gap-5" data-test="correction-form">
                <div>
                    <flux:heading size="lg">{{ __('Correct attendance') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('The labor cost is recomputed from the saved hourly rate and shift.') }}</flux:text>
                </div>

                {{-- The attendance being corrected --}}
                <div class="flex items-start gap-3 rounded-xl bg-brand-page px-3 py-2.5">
                    <x-avatar :name="$attendance->user->name" :initials="$attendance->user->initials()" size="sm" />
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="truncate font-semibold text-brand-navy">{{ $attendance->user->listName() }}</p>
                        <p class="truncate text-zinc-600">{{ $attendance->project->name }}</p>
                        <p class="tabular-nums text-zinc-700">
                            {{ $attendance->clock_in_at->format('M j, g:i A') }} &rarr;
                            @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
                        </p>
                    </div>
                    @include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])
                </div>

                <flux:error name="attendance" />

                <flux:radio.group wire:model.live="action" variant="pills" :label="__('Correction')" data-test="correction-action">
                    @foreach ($actions as $value => $label)
                        <flux:radio :value="$value" :label="__($label)" />
                    @endforeach
                </flux:radio.group>

                @switch ($action)
                    @case (\App\Livewire\Attendance\Correction::SET_CLOCK_OUT)
                        <flux:input type="datetime-local" wire:model="clock_out" :label="__('Clock-out')" :max="now()->format('Y-m-d\TH:i')" />
                        @break

                    @case (\App\Livewire\Attendance\Correction::CHANGE_TIMES)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input type="datetime-local" wire:model="clock_in" :label="__('Clock-in')" :max="now()->format('Y-m-d\TH:i')" />
                            <flux:input type="datetime-local" wire:model="clock_out" :label="__('Clock-out')" :max="now()->format('Y-m-d\TH:i')" />
                        </div>
                        @break

                    @case (\App\Livewire\Attendance\Correction::CHANGE_PROJECT)
                        <flux:select wire:model="project_id" :label="__('Project')">
                            @foreach ($this->projects as $project)
                                <flux:select.option :value="$project->id">{{ $project->code }} · {{ $project->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @break

                    @case (\App\Livewire\Attendance\Correction::SPLIT)
                        <flux:input
                            type="datetime-local"
                            wire:model="split_at"
                            :label="__('Split at')"
                            :description="__('The attendance ends at this time; a second one runs from this time to the clock-out, on the same project and rate.')"
                            :min="$attendance->clock_in_at->format('Y-m-d\TH:i')"
                            :max="$attendance->clock_out_at?->format('Y-m-d\TH:i')"
                        />
                        @break

                    @case (\App\Livewire\Attendance\Correction::VOID)
                        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('A void attendance no longer counts toward hours or project expenses.')" />
                        @break
                @endswitch

                <flux:textarea wire:model="reason" :label="__('Reason')" :placeholder="__('Why is this attendance being corrected?')" rows="2" :description="__('At least :min characters.', ['min' => \App\Services\AttendanceCorrectionService::MIN_REASON_LENGTH])" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" :variant="$action === \App\Livewire\Attendance\Correction::VOID ? 'danger' : 'primary'" data-test="save-correction">
                        {{ $action === \App\Livewire\Attendance\Correction::VOID ? __('Void attendance') : __('Save correction') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Compare with original: each correction that led to the record, oldest first --}}
    <flux:modal name="attendance-original" class="w-full max-w-2xl">
        <div class="flex flex-col gap-5" data-test="correction-history">
            <div>
                <flux:heading size="lg">{{ __('Compare with original') }}</flux:heading>
                <flux:text class="mt-1">{{ __('The original attendance is archived as it was; only the corrected record counts toward hours and project expenses.') }}</flux:text>
            </div>

            @foreach ($this->history as $step)
                <section class="flex flex-col gap-3" wire:key="correction-step-{{ $step['before']->id }}">
                    <div class="flex items-start gap-3 rounded-xl bg-brand-page px-3 py-2.5 text-sm">
                        <flux:icon.pencil-square class="mt-0.5 size-4 shrink-0 text-brand-blue" />
                        <div class="min-w-0">
                            <p class="font-semibold text-brand-navy">
                                {{ $step['by']?->name ?? __('Unknown') }}
                                <span class="font-normal text-zinc-500">&middot; {{ $step['at']?->format('M j, Y g:i A') }}</span>
                            </p>
                            <p class="text-zinc-700">{{ $step['reason'] }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $loop->first ? __('Original') : __('Before') }}</p>
                            @include('livewire.attendance.partials.correction-version', ['attendance' => $step['before'], 'archived' => true, 'test' => 'original-'.$step['before']->id])
                        </div>
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-blue">{{ $step['after']->count() > 1 ? __('Corrected (split)') : __('Corrected') }}</p>
                            @foreach ($step['after'] as $version)
                                @include('livewire.attendance.partials.correction-version', ['attendance' => $version, 'archived' => $version->trashed(), 'test' => 'corrected-'.$version->id])
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
