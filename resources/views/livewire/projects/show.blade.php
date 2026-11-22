{{-- Project page (T-16): running expenses with budget used, and the labor and materials breakdowns in tabs. --}}
@php($money = fn ($amount) => \App\Support\Money::format($amount))

<div class="flex flex-col gap-6">
    <x-page-header
        :title="$project->name"
        :description="collect([$project->code, $project->location, $project->client_name])->filter()->join(' · ')"
        :back="route('projects.index')"
        :back-label="__('Back to projects')"
    >
        <x-slot:badge>
            <x-status-badge :color="$project->statusColor()">{{ __($project->statusLabel()) }}</x-status-badge>
        </x-slot:badge>

        @if ($canManage)
            <x-slot:actions>
                <flux:button icon="pencil-square" :href="route('projects.edit', $project)" wire:navigate data-test="edit-project-button">{{ __('Edit project') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Filter toolbar: the date range applies to the totals and every tab --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 sm:grid-cols-2 lg:max-w-xl">
            <flux:input type="date" wire:model.live="from" :label="__('From')" data-test="filter-from" />
            <flux:input type="date" wire:model.live="to" :label="__('To')" data-test="filter-to" />
        </div>

        @if ($hasFilters)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
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

    {{-- Totals (not stat cards: those are only on the Dashboard). Running expenses is the hero tile on the
         navy blueprint band with the budget used; Labor and Materials sit beside it with their share. --}}
    @php($totals = $this->totals)
    @php($shares = $this->shares)
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" aria-label="{{ __('Running expenses') }}" data-test="expense-totals">
        <div class="bg-hero-blueprint relative flex flex-col justify-between gap-6 overflow-hidden rounded-card p-5 text-white shadow-card sm:p-7">
            <div>
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-blue-light">
                    <flux:icon icon="calculator" class="size-4" /> {{ __('Running expenses') }}
                </p>
                <p class="mt-2 text-4xl font-bold tabular-nums tracking-tight text-brand-gold sm:text-5xl" data-test="total-running">{{ $money($totals['running']) }}</p>
                @php($date = fn (string $value) => $value === '' ? '…' : \Illuminate\Support\Carbon::parse($value)->format('M j, Y'))
                <p class="mt-1 text-sm text-white/70" data-test="range-label">
                    {{ __('Labor + materials') }} ·
                    {{ $from === '' && $to === '' ? __('All dates') : $date($from).' – '.$date($to) }}
                </p>
            </div>

            @if ($this->budget)
                @php($budget = $this->budget)
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-white/70">{{ __('Budget') }} <span class="font-semibold tabular-nums text-white">{{ $money($project->budget) }}</span></span>
                        <span @class([
                            'rounded-full px-2 py-0.5 font-semibold tabular-nums',
                            'text-white' => $budget['level'] === 'normal',
                            'bg-brand-gold text-brand-navy' => $budget['level'] === 'warning',
                            'bg-danger text-white' => $budget['level'] === 'over',
                        ])>{{ __(':percent% used', ['percent' => $budget['percent']]) }}</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-white/15" role="progressbar" aria-label="{{ __('Budget used') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $budget['percent'] }}" data-test="budget-bar" data-level="{{ $budget['level'] }}">
                        <div @class([
                            'h-full rounded-full transition-all duration-200',
                            'bg-brand-blue-light' => $budget['level'] === 'normal',
                            'bg-brand-gold' => $budget['level'] === 'warning',
                            'bg-danger' => $budget['level'] === 'over',
                        ]) style="width: {{ $budget['width'] }}%"></div>
                    </div>
                </div>
            @else
                <p class="text-sm text-white/70">{{ __('No budget set') }}</p>
            @endif
        </div>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            @foreach (['labor' => [__('Labor'), 'wrench-screwdriver'], 'materials' => [__('Materials'), 'cube']] as $key => [$label, $icon])
                <div class="flex flex-col justify-between gap-3 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral">{{ $label }}</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-brand-blue" data-test="total-{{ $key }}">{{ $money($totals[$key]) }}</dd>
                        </div>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand-blue" aria-hidden="true">
                            <flux:icon :icon="$icon" class="size-5" />
                        </span>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-zinc-500" data-test="share-{{ $key }}">{{ __(':percent% of expenses', ['percent' => $shares[$key]]) }}</p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-brand-tint" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand-blue" style="width: {{ $shares[$key] }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Tabs (Flux free has no tabs component) --}}
    <div class="flex gap-1 overflow-x-auto rounded-card bg-white p-1 shadow-card ring-1 ring-brand-navy/5" role="tablist" aria-label="{{ __('Expense breakdowns') }}">
        @foreach ($tabs as $key => [$label, $icon])
            <button
                type="button"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'flex flex-1 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors duration-150 sm:flex-none',
                    'border-brand-gold bg-brand-tint text-brand-navy' => $tab === $key,
                    'border-transparent text-zinc-500 hover:bg-brand-page hover:text-brand-navy' => $tab !== $key,
                ])
                data-test="tab-{{ $key }}"
            >
                <flux:icon :icon="$icon" class="size-4" />
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    @php($rows = $this->rows)
    <x-data-card>
        @if ($rows->isEmpty())
            @if (str_starts_with($tab, 'labor'))
                <x-empty-state icon="clock" :title="__('No labor yet')" :description="$hasFilters ? __('No Clocked Out attendance on this project in these dates.') : __('Labor shows here once workers clock out on this project.')" />
            @else
                <x-empty-state icon="shopping-cart" :title="__('No material purchases yet')" :description="$hasFilters ? __('No material purchases for this project in these dates.') : __('Purchases recorded under Material Entries show here.')" />
            @endif
        @else
            @include('livewire.projects.partials.expense-'.$tab, ['rows' => $rows, 'money' => $money])
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$rows" />
        </div>
    </x-data-card>
</div>
