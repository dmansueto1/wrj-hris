{{-- "Needs correction" (T-13): every flagged attendance (not clocked out past the flag time in System Settings), any date, oldest first. Admin corrects from the row menu. --}}
<div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
    <div class="flex flex-wrap items-center gap-2" data-test="active-filters">
        <x-filter-chip :label="__('Needs correction')" wire:click="clearFilters" />
        <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
            {{ __('Clear filters') }}
        </button>
    </div>
    <p class="mt-2 text-sm text-zinc-600">{{ __('Attendance still Clocked In more than :hours hours after clock-in. Set the clock-out (or void it) so its hours and labor cost count.', ['hours' => \App\Support\Settings::int('attendance_flag_after_hours')]) }}</p>
</div>

<x-data-card>
    @if ($this->flagged->isEmpty())
        <x-empty-state icon="check-circle" :title="__('Nothing needs correction.')" :description="__('Every attendance has a clock-out.')">
            <flux:button size="sm" wire:click="clearFilters">{{ __('Back to all attendance') }}</flux:button>
        </x-empty-state>
    @else
        {{-- Desktop / tablet --}}
        <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
            <flux:table>
                <flux:table.columns sticky class="bg-white">
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Clock In') }}</flux:table.column>
                    <flux:table.column>{{ __('Project') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    @can('attendance.correct')
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    @endcan
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->flagged as $attendance)
                        <flux:table.row :key="'flagged-'.$attendance->id" :data-test="'flagged-row-'.$attendance->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <x-avatar :name="$attendance->user->name" :initials="$attendance->user->initials()" size="sm" />
                                    <span class="font-semibold text-brand-navy">{{ $attendance->user->listName() }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $attendance->work_date->format('D, M j, Y') }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }}</flux:table.cell>
                            <flux:table.cell>{{ $attendance->project->name }}</flux:table.cell>
                            <flux:table.cell>@include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])</flux:table.cell>
                            @can('attendance.correct')
                                <flux:table.cell align="end">
                                    @include('livewire.attendance.partials.correct-menu', ['attendances' => collect([$attendance]), 'key' => 'flagged-'.$attendance->id])
                                </flux:table.cell>
                            @endcan
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Phone --}}
        <ul class="divide-y divide-zinc-100 md:hidden">
            @foreach ($this->flagged as $attendance)
                <li wire:key="flagged-card-{{ $attendance->id }}" class="flex items-start gap-3 p-4">
                    <x-avatar :name="$attendance->user->name" :initials="$attendance->user->initials()" size="sm" />
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="truncate font-semibold text-brand-navy">{{ $attendance->user->listName() }}</p>
                        <p class="text-zinc-600">{{ $attendance->work_date->format('D, M j, Y') }} &middot; <span class="tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }}</span></p>
                        <p class="truncate text-zinc-500">{{ $attendance->project->name }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])
                        @include('livewire.attendance.partials.correct-menu', ['attendances' => collect([$attendance]), 'key' => 'flagged-'.$attendance->id])
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
        <x-table-footer :paginator="$this->flagged" />
    </div>
</x-data-card>
