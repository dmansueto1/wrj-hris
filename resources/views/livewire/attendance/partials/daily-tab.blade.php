{{-- Daily attendance tab (T-11.4): every employee on one date, Present or Absent; filter by employee or project, sort by name, project or worked time. --}}
@php($isToday = $date === $today)

<div class="grid gap-4 sm:grid-cols-3">
    <x-stat-card icon="users" color="indigo" :label="__('Employees')" :value="number_format($this->dailySummary['employees'])" :note="\Illuminate\Support\Carbon::parse($date)->format('l, M j, Y')" />
    <x-stat-card icon="check-circle" color="green" :label="__('Present')" :value="number_format($this->dailySummary['present'])" :note="__('With attendance this day')" />
    <x-stat-card icon="minus-circle" color="zinc" :label="__('Absent')" :value="number_format($this->dailySummary['absent'])" :note="__('No attendance this day')" />
</div>

{{-- Filters --}}
<div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input class="sm:w-44" type="date" wire:model.live="date" :aria-label="__('Date')" data-test="filter-date" />

        <flux:select wire:model.live="user" :aria-label="__('Filter by employee')" data-test="filter-user">
            <flux:select.option value="">{{ __('All employees') }}</flux:select.option>
            @foreach ($this->users as $person)
                <flux:select.option :value="$person->id">{{ $person->listName() }} ({{ $person->employee_no }})</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="project" :aria-label="__('Filter by project')" data-test="filter-project">
            <flux:select.option value="">{{ __('All projects') }}</flux:select.option>
            @foreach ($this->projects as $proj)
                <flux:select.option :value="$proj->id">{{ $proj->code }} · {{ $proj->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="sortBy" :aria-label="__('Sort by')" data-test="filter-sort-by">
            <flux:select.option value="name">{{ __('Sort by Name') }}</flux:select.option>
            <flux:select.option value="project">{{ __('Sort by Project') }}</flux:select.option>
            <flux:select.option value="worked_time">{{ __('Sort by Worked time') }}</flux:select.option>
        </flux:select>
    </div>

    @if (! $isToday || $user !== '' || $project !== '')
        <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
            @unless ($isToday)
                <x-filter-chip :label="__('Date: :value', ['value' => \Illuminate\Support\Carbon::parse($date)->format('M j, Y')])" wire:click="$set('date', '{{ $today }}')" />
            @endunless
            @if ($user !== '')
                <x-filter-chip :label="__('Employee: :value', ['value' => $this->users->firstWhere('id', (int) $user)?->listName()])" wire:click="$set('user', '')" />
            @endif
            @if ($project !== '')
                <x-filter-chip :label="__('Project: :value', ['value' => $this->projectName])" wire:click="$set('project', '')" />
            @endif
            <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                {{ __('Clear filters') }}
            </button>
        </div>
    @endif
</div>

<x-data-card>
    @if ($this->daily->isEmpty())
        <x-empty-state icon="users" :title="__('No employees to show.')" :description="__('Choose another employee or clear the filters.')">
            <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        </x-empty-state>
    @else
        {{-- Desktop / tablet: one row per employee; their attendance under Morning and Afternoon --}}
        <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
            <flux:table>
                <flux:table.columns sticky class="bg-white">
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Morning In') }}</flux:table.column>
                    <flux:table.column>{{ __('Morning Out') }}</flux:table.column>
                    <flux:table.column>{{ __('Afternoon In') }}</flux:table.column>
                    <flux:table.column>{{ __('Afternoon Out') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
                    <flux:table.column>{{ __('Photos') }}</flux:table.column>
                    <flux:table.column class="w-32">{{ __('Remarks') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    @can('attendance.correct')
                        <flux:table.column align="end"><span>{{ __('Actions') }}</span></flux:table.column>
                    @endcan
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->daily as $row)
                        <flux:table.row :key="'daily-'.$row['user']->id" :data-test="'employee-row-'.$row['user']->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <x-avatar :name="$row['user']->name" :initials="$row['user']->initials()" size="sm" />
                                    <span class="font-semibold text-brand-navy">{{ $row['user']->listName() }}</span>
                                </div>
                            </flux:table.cell>
                            @php($attendanceDay = \App\Support\AttendanceDay::of($row['attendances']))
                            @include('livewire.attendance.partials.day-cells', ['day' => $attendanceDay, 'photos' => true])
                            <flux:table.cell class="w-32">@include('livewire.attendance.partials.day-remarks', ['day' => $attendanceDay])</flux:table.cell>
                            <flux:table.cell>
                                @include('livewire.attendance.partials.presence-badge', ['present' => $row['present'], 'future' => $row['future']])
                            </flux:table.cell>
                            @can('attendance.correct')
                                <flux:table.cell align="end">
                                    @include('livewire.attendance.partials.correct-menu', ['attendances' => $row['attendances'], 'key' => $row['user']->id])
                                </flux:table.cell>
                            @endcan
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Phone: one card per employee --}}
        <ul class="divide-y divide-zinc-100 md:hidden">
            @foreach ($this->daily as $row)
                <li wire:key="daily-card-{{ $row['user']->id }}" class="flex flex-col gap-2 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-avatar :name="$row['user']->name" :initials="$row['user']->initials()" size="sm" />
                            <span class="truncate font-semibold text-brand-navy">{{ $row['user']->listName() }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @include('livewire.attendance.partials.presence-badge', ['present' => $row['present'], 'future' => $row['future']])
                            @include('livewire.attendance.partials.correct-menu', ['attendances' => $row['attendances'], 'key' => $row['user']->id])
                        </div>
                    </div>
                    @foreach ($row['attendances'] as $attendance)
                        @include('livewire.attendance.partials.attendance-line', ['attendance' => $attendance])
                    @endforeach
                </li>
            @endforeach
        </ul>
    @endif

    <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
        <x-table-footer :paginator="$this->daily" />
    </div>
</x-data-card>
