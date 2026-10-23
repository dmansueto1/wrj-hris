<div class="flex flex-col gap-6">
    <x-page-header :title="__('Projects')" :description="__('Construction sites workers clock in to. Only Ongoing projects appear at clock-in.')">
        @if ($canManage)
            <x-slot:actions>
                <flux:button icon="plus" :href="route('projects.create')" wire:navigate class="btn-accent" data-test="add-project-button">
                    {{ __('Add project') }}
                </flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card icon="building-office-2" color="blue" :label="__('Total projects')" :value="number_format($this->summary['total'])" :note="__(':count in planning', ['count' => $this->summary['statuses']['planning']])" />
        <x-stat-card icon="bolt" color="green" :label="__('Ongoing')" :value="number_format($this->summary['statuses']['ongoing'])" :note="__('Open for clock-in')" />
        <x-stat-card icon="pause-circle" color="amber" :label="__('On hold')" :value="number_format($this->summary['statuses']['on_hold'])" :note="__('Clock-in closed')" />
        <x-stat-card icon="check-circle" color="indigo" :label="__('Completed')" :value="number_format($this->summary['statuses']['completed'])" :note="__('Finished projects')" />
    </div>

    {{-- Filter toolbar with view toggle --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 md:grid-cols-[1fr_12rem_auto]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search code or name')"
                :aria-label="__('Search projects')"
                clearable
            />

            <flux:select wire:model.live="status" :aria-label="__('Filter by status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($statuses as $value => [$label])
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="hidden items-center rounded-lg bg-neutral-soft p-1 md:flex" role="group" aria-label="{{ __('View') }}">
                <button type="button" wire:click="$set('view', 'grid')" aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}"
                    @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-150', 'bg-white text-brand-navy shadow-card' => $view === 'grid', 'text-zinc-500 hover:text-brand-navy' => $view !== 'grid'])
                    data-test="view-grid">
                    <flux:icon icon="squares-2x2" class="size-4" /> {{ __('Cards') }}
                </button>
                <button type="button" wire:click="$set('view', 'table')" aria-pressed="{{ $view === 'table' ? 'true' : 'false' }}"
                    @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-150', 'bg-white text-brand-navy shadow-card' => $view === 'table', 'text-zinc-500 hover:text-brand-navy' => $view !== 'table'])
                    data-test="view-table">
                    <flux:icon icon="table-cells" class="size-4" /> {{ __('Table') }}
                </button>
            </div>
        </div>

        @if ($hasFilters)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                @if (trim($search) !== '')
                    <x-filter-chip :label="__('Search: :value', ['value' => $search])" wire:click="$set('search', '')" />
                @endif
                @if ($status !== '' && isset($statuses[$status]))
                    <x-filter-chip :label="__('Status: :value', ['value' => __($statuses[$status][0])])" wire:click="$set('status', '')" />
                @endif
                <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endif
    </div>

    @if ($this->projects->isEmpty())
        <x-data-card>
            @if ($hasFilters)
                <x-empty-state icon="magnifying-glass" :title="__('No projects match your search.')" :description="__('Try a different code or name, or clear the filters.')">
                    <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="building-office-2" :title="__('No projects yet')" :description="__('Add a project and set it to Ongoing so workers can clock in to it.')">
                    @if ($canManage)
                        <flux:button size="sm" icon="plus" :href="route('projects.create')" wire:navigate class="btn-accent">{{ __('Add project') }}</flux:button>
                    @endif
                </x-empty-state>
            @endif
        </x-data-card>
    @else
        {{-- Cards: always on phones, and on larger screens when "Cards" is chosen --}}
        <div @class(['grid gap-4 sm:grid-cols-2 xl:grid-cols-3', 'md:hidden' => $view === 'table'])>
            @foreach ($this->projects as $project)
                @php($progress = $project->scheduleProgress())
                <article wire:key="project-card-{{ $project->id }}" class="flex flex-col gap-4 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 transition-shadow duration-200 hover:shadow-card-hover">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="rounded-md bg-neutral-soft px-2 py-0.5 font-mono text-xs text-neutral">{{ $project->code }}</span>
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="mt-2 block truncate text-lg font-bold text-brand-navy hover:text-brand-blue">{{ $project->name }}</a>
                        </div>
                        <x-status-badge :color="$project->statusColor()">{{ __($project->statusLabel()) }}</x-status-badge>
                    </div>

                    <dl class="grid gap-1.5 text-sm text-zinc-600">
                        <div class="flex items-center gap-2"><flux:icon icon="map-pin" class="size-4 text-zinc-400" /><dt class="sr-only">{{ __('Location') }}</dt><dd class="truncate">{{ $project->location }}</dd></div>
                        <div class="flex items-center gap-2"><flux:icon icon="briefcase" class="size-4 text-zinc-400" /><dt class="sr-only">{{ __('Client') }}</dt><dd class="truncate">{{ $project->client_name ?? '—' }}</dd></div>
                        <div class="flex items-center gap-2"><flux:icon icon="banknotes" class="size-4 text-zinc-400" /><dt class="sr-only">{{ __('Budget') }}</dt><dd class="tabular-nums">{{ $project->budget !== null ? \App\Support\Money::format($project->budget) : __('No budget set') }}</dd></div>
                    </dl>

                    <div class="mt-auto">
                        <div class="mb-1 flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ $project->start_date?->format('M j, Y') ?? '—' }} &rarr; {{ $project->end_date?->format('M j, Y') ?? '—' }}</span>
                            @if ($progress !== null)
                                <span class="font-semibold tabular-nums text-brand-navy">{{ __(':percent% of schedule', ['percent' => $progress]) }}</span>
                            @endif
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-neutral-soft" role="progressbar" aria-label="{{ __('Schedule') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress ?? 0 }}">
                            <div class="h-full rounded-full bg-brand-gold transition-all duration-200" style="width: {{ $progress ?? 0 }}%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-zinc-100 pt-3">
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="text-sm font-medium text-brand-blue hover:text-brand-blue-dark">{{ __('View project') }} &rarr;</a>
                        @if ($canManage)
                            @include('livewire.projects.partials.row-actions', ['project' => $project, 'testIds' => $view === 'grid'])
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Table: larger screens when "Table" is chosen --}}
        @if ($view === 'table')
            <x-data-card class="hidden md:block">
                <div class="max-h-[70vh] overflow-auto px-4 sm:px-6">
                    <flux:table>
                        <flux:table.columns sticky class="bg-white">
                            <flux:table.column>{{ __('Project') }}</flux:table.column>
                            <flux:table.column>{{ __('Location') }}</flux:table.column>
                            <flux:table.column>{{ __('Client') }}</flux:table.column>
                            <flux:table.column>{{ __('Schedule') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Budget') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            @if ($canManage)
                                <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                            @endif
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->projects as $project)
                                <flux:table.row :key="$project->id">
                                    <flux:table.cell>
                                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="group block">
                                            <span class="block font-semibold text-brand-navy group-hover:text-brand-blue">{{ $project->name }}</span>
                                            <span class="font-mono text-xs text-zinc-500">{{ $project->code }}</span>
                                        </a>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $project->location }}</flux:table.cell>
                                    <flux:table.cell>{{ $project->client_name ?? '—' }}</flux:table.cell>
                                    <flux:table.cell class="text-xs">{{ $project->start_date?->format('M j, Y') ?? '—' }} &rarr; {{ $project->end_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        @if ($project->budget !== null)
                                            <span class="font-medium text-brand-navy">{{ \App\Support\Money::format($project->budget) }}</span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <x-status-badge :color="$project->statusColor()">{{ __($project->statusLabel()) }}</x-status-badge>
                                    </flux:table.cell>
                                    @if ($canManage)
                                        <flux:table.cell align="end">
                                            @include('livewire.projects.partials.row-actions', ['project' => $project, 'testIds' => true])
                                        </flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </x-data-card>
        @endif
    @endif

    <div class="rounded-card bg-white px-4 pb-3 shadow-card ring-1 ring-brand-navy/5 sm:px-6">
        <x-table-footer :paginator="$this->projects" />
    </div>
</div>
