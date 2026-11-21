{{-- Daily attendance tab (T-11.4, no stat cards since T-15.1): every employee on one date, Present or Absent; filter by employee or project, sort by name, project or worked time. --}}
@php($isToday = $date === $today)

{{-- Filters --}}
<div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input class="sm:w-44 sm:shrink-0" type="date" wire:model.live="date" :aria-label="__('Date')" data-test="filter-date" />

        <x-searchable-select
            model="user"
            :value="$user"
            :options="$this->employeeOptions"
            :empty-label="__('All employees')"
            :search-placeholder="__('Search name or employee no.')"
            :label="__('Filter by employee')"
            class="sm:min-w-0 sm:flex-[2]"
            data-test="filter-user"
        />
        <flux:select class="sm:min-w-0 sm:flex-1" wire:model.live="project" :aria-label="__('Filter by project')" data-test="filter-project">
            <flux:select.option value="">{{ __('All projects') }}</flux:select.option>
            @foreach ($this->projects as $proj)
                <flux:select.option :value="$proj->id">{{ $proj->code }} · {{ $proj->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select class="sm:min-w-0 sm:flex-1" wire:model.live="sortBy" :aria-label="__('Sort by')" data-test="filter-sort-by">
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
        @include('livewire.attendance.partials.daily-table', ['rows' => $this->daily, 'correctable' => auth()->user()->can('attendance.correct')])
    @endif

    <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
        <x-table-footer :paginator="$this->daily" />
    </div>
</x-data-card>
