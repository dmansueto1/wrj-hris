<div class="flex flex-col gap-6">
    <x-page-header :title="__('Materials')" :description="__('Materials with their unit and default price, used when recording purchases.')">
        <x-slot:actions>
            <flux:button icon="plus" :href="route('materials.create')" wire:navigate class="btn-accent" data-test="add-material-button">
                {{ __('Add material') }}
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card icon="cube" color="blue" :label="__('Total materials')" :value="number_format($this->summary['total'])" :note="__('Archived materials not counted')" />
        <x-stat-card icon="check-badge" color="green" :label="__('Active')" :value="number_format($this->summary['active'])" :note="__('Can be picked for new entries')" />
        <x-stat-card icon="no-symbol" color="zinc" :label="__('Inactive')" :value="number_format($this->summary['inactive'])" :note="__('Hidden from new entries')" />
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 md:grid-cols-[1fr_12rem]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search code or name')"
                :aria-label="__('Search materials')"
                clearable
            />

            <flux:select wire:model.live="status" :aria-label="__('Filter by status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($hasFilters)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                @if (trim($search) !== '')
                    <x-filter-chip :label="__('Search: :value', ['value' => $search])" wire:click="$set('search', '')" />
                @endif
                @if ($status !== '')
                    <x-filter-chip :label="__('Status: :value', ['value' => __(ucfirst($status))])" wire:click="$set('status', '')" />
                @endif
                <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endif
    </div>

    <x-data-card>
        @if ($this->materials->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="magnifying-glass" :title="__('No materials match your search.')" :description="__('Try a different code or name, or clear the filters.')">
                    <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="cube" :title="__('No materials yet')" :description="__('Add the materials you buy most, like hollow blocks or cement, to speed up material entries.')">
                    <flux:button size="sm" icon="plus" :href="route('materials.create')" wire:navigate class="btn-accent">{{ __('Add material') }}</flux:button>
                </x-empty-state>
            @endif
        @else
            {{-- Desktop / tablet: table --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Material') }}</flux:table.column>
                        <flux:table.column>{{ __('Code') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Default price') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->materials as $material)
                            <flux:table.row :key="$material->id">
                                <flux:table.cell>
                                    <a href="{{ route('materials.edit', $material) }}" wire:navigate class="font-semibold text-brand-navy hover:text-brand-blue">{{ $material->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="rounded-md bg-neutral-soft px-2 py-0.5 font-mono text-xs text-neutral">{{ $material->code }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $material->unit }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="font-medium text-brand-navy">{{ \App\Support\Money::format($material->default_price) }}</span>
                                    <span class="text-xs text-zinc-500">/ {{ $material->unit }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @include('livewire.materials.partials.status-badge', ['material' => $material])
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @include('livewire.materials.partials.row-actions', ['material' => $material])
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: stacked cards --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->materials as $material)
                    <li wire:key="material-card-{{ $material->id }}" class="flex items-start gap-3 p-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning-soft text-brand-gold">
                            <flux:icon icon="cube" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('materials.edit', $material) }}" wire:navigate class="block truncate font-semibold text-brand-navy">{{ $material->name }}</a>
                            <p class="truncate text-xs text-zinc-500"><span class="font-mono">{{ $material->code }}</span> &middot; {{ $material->unit }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium tabular-nums text-brand-navy">{{ \App\Support\Money::format($material->default_price) }} / {{ $material->unit }}</span>
                                @include('livewire.materials.partials.status-badge', ['material' => $material])
                            </div>
                        </div>
                        @include('livewire.materials.partials.row-actions', ['material' => $material])
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->materials" />
        </div>
    </x-data-card>
</div>
