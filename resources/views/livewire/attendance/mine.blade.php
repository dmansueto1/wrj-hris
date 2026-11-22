{{-- My Attendance (T-11, T-11.4; no stat cards since T-15.1): every day of the range, Present or Absent like All Attendance. Hours only: no peso amounts, no photos. --}}
<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Attendance')" :description="__('Your attendance by day, with the hours worked on each project.')" />

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 sm:grid-cols-2 md:max-w-xl">
            <flux:input type="date" wire:model.live="from" :label="__('From')" data-test="filter-from" />
            <flux:input type="date" wire:model.live="to" :label="__('To')" data-test="filter-to" />
        </div>

        @if ($this->range['shortened'])
            <p class="mt-3 text-xs text-warning" data-test="range-shortened">
                {{ __('Showing the last 92 days of the range: :from to :to.', [
                    'from' => \Illuminate\Support\Carbon::parse($this->range['from'])->format('M j, Y'),
                    'to' => \Illuminate\Support\Carbon::parse($this->range['to'])->format('M j, Y'),
                ]) }}
            </p>
        @endif

        @unless ($isThisWeek)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                <x-filter-chip
                    :label="__('Dates: :from to :to', ['from' => \Illuminate\Support\Carbon::parse($from)->format('M j, Y'), 'to' => \Illuminate\Support\Carbon::parse($to)->format('M j, Y')])"
                    wire:click="thisWeek"
                />
                <button type="button" wire:click="thisWeek" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endunless
    </div>

    <x-data-card>
        @if ($this->days->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('No days on this page.')" :description="__('Go back to the first page or pick other dates.')" />
        @else
            @include('livewire.attendance.partials.my-days-table', ['days' => $this->days, 'totalHours' => $this->totals['hours'], 'totalLate' => $this->totals['late']])
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->days" />
        </div>
    </x-data-card>
</div>
