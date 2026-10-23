{{-- All Attendance (T-11): everyone's attendance with labor cost and photos, for Admin and Staff. --}}
<div class="flex flex-col gap-6" x-data="{ photo: { src: '', label: '' } }">
    <x-page-header :title="__('All Attendance')" :description="__('Every attendance record with hours, labor cost and the clock-in and clock-out photos.')" />

    {{-- Stat cards: totals cover every filtered record, not just this page --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card icon="clipboard-document-check" color="indigo" :label="__('Attendance records')" :value="number_format($this->attendances->total())" :note="__('Matching the filters')" />
        <x-stat-card icon="clock" color="blue" :label="__('Total hours')" :value="$this->totals['hours']" :note="__('Matching the filters')" />
        <x-stat-card icon="banknotes" color="amber" :label="__('Labor cost')" :value="\App\Support\Money::format($this->totals['labor_cost'])" :note="__('Matching the filters')" />
        <x-stat-card icon="user-group" color="green" :label="__('Clocked In now')" :value="number_format($clockedInNow)" :note="__('Across all projects')" />
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input type="date" wire:model.live="from" :aria-label="__('From')" data-test="filter-from" />
            <flux:input type="date" wire:model.live="to" :aria-label="__('To')" data-test="filter-to" />

            <flux:select wire:model.live="user" :aria-label="__('Filter by employee')" data-test="filter-user">
                <flux:select.option value="">{{ __('All employees') }}</flux:select.option>
                @foreach ($this->users as $person)
                    <flux:select.option :value="$person->id">{{ $person->name }} ({{ $person->employee_no }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="project" :aria-label="__('Filter by project')" data-test="filter-project">
                <flux:select.option value="">{{ __('All projects') }}</flux:select.option>
                @foreach ($this->projects as $item)
                    <flux:select.option :value="$item->id">{{ $item->code }} · {{ $item->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" :aria-label="__('Filter by status')" data-test="filter-status">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($statuses as $value => [$label])
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($hasFilters || ! $isThisWeek)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                @unless ($isThisWeek)
                    <x-filter-chip
                        :label="__('Dates: :from to :to', ['from' => \Illuminate\Support\Carbon::parse($from)->format('M j, Y'), 'to' => \Illuminate\Support\Carbon::parse($to)->format('M j, Y')])"
                        wire:click="clearDates"
                    />
                @endunless
                @if ($user !== '')
                    <x-filter-chip :label="__('Employee: :value', ['value' => $this->users->firstWhere('id', (int) $user)?->name])" wire:click="$set('user', '')" />
                @endif
                @if ($project !== '')
                    <x-filter-chip :label="__('Project: :value', ['value' => $this->projects->firstWhere('id', (int) $project)?->code])" wire:click="$set('project', '')" />
                @endif
                @if ($status !== '')
                    <x-filter-chip :label="__('Status: :value', ['value' => __($statuses[$status][0] ?? $status)])" wire:click="$set('status', '')" />
                @endif
                <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endif
    </div>

    <x-data-card>
        @if ($this->attendances->isEmpty())
            <x-empty-state icon="table-cells" :title="__('No attendance matches these filters.')" :description="__('Try other dates or clear the filters.')">
                <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            </x-empty-state>
        @else
            {{-- Desktop / tablet: table --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Project') }}</flux:table.column>
                        <flux:table.column>{{ __('In') }}</flux:table.column>
                        <flux:table.column>{{ __('Out') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Rate') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Labor cost') }}</flux:table.column>
                        <flux:table.column>{{ __('Photos') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->attendances as $attendance)
                            @php($void = $attendance->status === \App\Models\Attendance::STATUS_VOID)
                            <flux:table.row :key="$attendance->id" :class="$void ? 'opacity-60' : ''" data-test="attendance-row-{{ $attendance->id }}">
                                <flux:table.cell class="whitespace-nowrap">{{ $attendance->work_date->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <x-avatar :name="$attendance->user?->name" :initials="$attendance->user?->initials()" size="sm" />
                                        <span class="font-semibold text-brand-navy">{{ $attendance->user?->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="block max-w-48 truncate">{{ $attendance->project->name }}</span>
                                    <span class="font-mono text-xs text-zinc-500">{{ $attendance->project->code }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap tabular-nums">
                                    @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance])</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ \App\Support\Money::format($attendance->hourly_rate) }}</flux:table.cell>
                                <flux:table.cell align="end" :class="'whitespace-nowrap font-medium tabular-nums text-brand-navy'.($void ? ' line-through' : '')">
                                    {{ $attendance->labor_cost !== null ? \App\Support\Money::format($attendance->labor_cost) : '—' }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
                                </flux:table.cell>
                                <flux:table.cell>
                                    @include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        {{-- Labor cost over every filtered record (all pages); void and Clocked In add nothing --}}
                        <flux:table.row class="bg-brand-tint/50">
                            <flux:table.cell colspan="7" class="font-semibold text-brand-navy">{{ __('Total') }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy"><span data-test="total-labor-cost">{{ \App\Support\Money::format($this->totals['labor_cost']) }}</span></flux:table.cell>
                            <flux:table.cell colspan="2"></flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: stacked cards --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->attendances as $attendance)
                    @php($void = $attendance->status === \App\Models\Attendance::STATUS_VOID)
                    <li wire:key="attendance-card-{{ $attendance->id }}" @class(['flex items-start gap-3 p-4', 'opacity-60' => $void])>
                        <x-avatar :name="$attendance->user?->name" :initials="$attendance->user?->initials()" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-brand-navy">{{ $attendance->user?->name }}</p>
                                    <p class="truncate text-xs text-zinc-500">{{ $attendance->project->name }}</p>
                                </div>
                                @include('livewire.attendance.partials.status-badge', ['attendance' => $attendance])
                            </div>
                            <p class="mt-1 text-sm tabular-nums text-zinc-700">
                                {{ $attendance->work_date->format('M j') }} &middot;
                                {{ $attendance->clock_in_at->format('g:i A') }} &rarr;
                                @include('livewire.attendance.partials.clock-out-time', ['attendance' => $attendance])
                            </p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
                                <p class="text-end text-sm tabular-nums">
                                    <span class="text-zinc-500">@include('livewire.attendance.partials.worked-time', ['attendance' => $attendance]) &times; {{ \App\Support\Money::format($attendance->hourly_rate) }}</span>
                                    <span @class(['block font-semibold text-brand-navy', 'line-through' => $void])>
                                        {{ $attendance->labor_cost !== null ? \App\Support\Money::format($attendance->labor_cost) : '—' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 bg-brand-tint/50 p-4 text-sm">
                    <span class="font-semibold text-brand-navy">{{ __('Total') }}</span>
                    <span class="font-bold tabular-nums text-brand-navy">{{ \App\Support\Money::format($this->totals['labor_cost']) }}</span>
                </li>
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->attendances" />
        </div>
    </x-data-card>

    {{-- One modal for every thumbnail: the clicked photo full size --}}
    <flux:modal name="attendance-photo" class="w-full max-w-lg">
        <div class="flex flex-col gap-4">
            <h2 class="pe-8 text-base font-bold text-brand-navy" x-text="photo.label"></h2>
            <img x-bind:src="photo.src" x-bind:alt="photo.label" class="max-h-[70vh] w-full rounded-xl bg-zinc-100 object-contain" />
        </div>
    </flux:modal>
</div>
