{{-- Material Entries (T-15): purchases per project with a totals row for everything matching the filters. --}}
@php($money = fn ($amount) => \App\Support\Money::format($amount))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Material Entries')" :description="__('Material purchases per project, with the markup added to each entry.')">
        <x-slot:actions>
            <flux:button icon="plus" :href="route('material-entries.create')" wire:navigate class="btn-accent" data-test="add-entry-button">
                {{ __('Record purchase') }}
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="project" :label="__('Project')" data-test="filter-project">
                <flux:select.option value="">{{ __('All projects') }}</flux:select.option>
                @foreach ($this->projectOptions as $option)
                    <flux:select.option :value="$option->id">{{ $option->code }} · {{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="material" :label="__('Material')" data-test="filter-material">
                <flux:select.option value="">{{ __('All materials') }}</flux:select.option>
                @foreach ($this->materialOptions as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" :label="__('From')" data-test="filter-from" />
            <flux:input type="date" wire:model.live="to" :label="__('To')" data-test="filter-to" />
        </div>

        @if ($hasFilters)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                @if ($project !== '')
                    <x-filter-chip :label="__('Project: :value', ['value' => $this->projectOptions->firstWhere('id', (int) $project)?->name ?? $project])" wire:click="$set('project', '')" />
                @endif
                @if ($material !== '')
                    <x-filter-chip :label="__('Material: :value', ['value' => $this->materialOptions->firstWhere('id', (int) $material)?->name ?? $material])" wire:click="$set('material', '')" />
                @endif
                @if ($from !== '')
                    <x-filter-chip :label="__('From: :value', ['value' => $from])" wire:click="$set('from', '')" />
                @endif
                @if ($to !== '')
                    <x-filter-chip :label="__('To: :value', ['value' => $to])" wire:click="$set('to', '')" />
                @endif
                <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endif
    </div>

    <x-data-card>
        @if ($this->entries->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="magnifying-glass" :title="__('No material purchases match the filters.')" :description="__('Try another project, material or date range, or clear the filters.')">
                    <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="shopping-cart" :title="__('No material purchases yet')" :description="__('Record a purchase to add its cost, with markup, to the project.')">
                    <flux:button size="sm" icon="plus" :href="route('material-entries.create')" wire:navigate class="btn-accent">{{ __('Record purchase') }}</flux:button>
                </x-empty-state>
            @endif
        @else
            {{-- Desktop / tablet: table --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Project') }}</flux:table.column>
                        <flux:table.column>{{ __('Material') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Unit price') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Subtotal') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Markup') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                        <flux:table.column>{{ __('Entered by') }}</flux:table.column>
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row :key="$entry->id" data-test="entry-{{ $entry->id }}">
                                <flux:table.cell class="whitespace-nowrap">{{ $entry->entry_date->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="block font-semibold text-brand-navy">{{ $entry->project?->name }}</span>
                                    <span class="font-mono text-xs text-zinc-500">{{ $entry->project?->code }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="block">{{ $entry->material?->name }}</span>
                                    @if ($entry->remarks)
                                        <span class="block max-w-48 truncate text-xs text-zinc-500" title="{{ $entry->remarks }}">{{ $entry->remarks }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ $entry->quantity }} {{ $entry->material?->unit }}</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ $money($entry->unit_price) }}</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ $money($entry->subtotal) }}</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ $money($entry->markup_amount) }}</flux:table.cell>
                                <flux:table.cell align="end" class="whitespace-nowrap font-semibold tabular-nums text-brand-navy">{{ $money($entry->total_cost) }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <x-avatar :name="$entry->enteredBy?->name ?? '?'" :initials="$entry->enteredBy?->initials()" size="sm" />
                                        <span class="whitespace-nowrap">{{ $entry->enteredBy?->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @include('livewire.material-entries.partials.row-actions', ['entry' => $entry])
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        <flux:table.row class="bg-brand-tint/50" data-test="totals-row">
                            <flux:table.cell colspan="5" class="font-semibold text-brand-navy">{{ __('Total (all pages)') }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy">{{ $money($this->totals['subtotal']) }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy">{{ $money($this->totals['markup']) }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap font-bold tabular-nums text-brand-navy">{{ $money($this->totals['total']) }}</flux:table.cell>
                            <flux:table.cell colspan="2"></flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: stacked cards --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->entries as $entry)
                    <li wire:key="entry-card-{{ $entry->id }}" class="flex items-start gap-3 p-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning-soft text-brand-gold">
                            <flux:icon icon="shopping-cart" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-brand-navy">{{ $entry->material?->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $entry->project?->name }} &middot; {{ $entry->entry_date->format('M j, Y') }}</p>
                            <p class="mt-1 text-sm tabular-nums text-zinc-700">
                                {{ $entry->quantity }} {{ $entry->material?->unit }} × {{ $money($entry->unit_price) }}
                            </p>
                            <dl class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                <div><dt class="text-zinc-500">{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($entry->subtotal) }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Markup') }}</dt><dd class="tabular-nums">{{ $money($entry->markup_amount) }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Total') }}</dt><dd class="font-semibold tabular-nums text-brand-navy">{{ $money($entry->total_cost) }}</dd></div>
                            </dl>
                            <p class="mt-2 flex items-center gap-1.5 text-xs text-zinc-500">
                                <x-avatar :name="$entry->enteredBy?->name ?? '?'" :initials="$entry->enteredBy?->initials()" size="sm" />
                                {{ $entry->enteredBy?->name }}
                            </p>
                        </div>
                        @include('livewire.material-entries.partials.row-actions', ['entry' => $entry])
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 bg-brand-tint/50 p-4 text-sm">
                    <span class="font-semibold text-brand-navy">{{ __('Total (all pages)') }}</span>
                    <span class="font-bold tabular-nums text-brand-navy">{{ $money($this->totals['total']) }}</span>
                </li>
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->entries" />
        </div>
    </x-data-card>
</div>
