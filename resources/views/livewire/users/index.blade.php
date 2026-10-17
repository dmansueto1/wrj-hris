<div class="flex flex-col gap-6">
    <x-page-header :title="__('Users')" :description="__('Employee accounts, roles and hourly rates.')">
        <x-slot:actions>
            <flux:button icon="plus" :href="route('users.create')" wire:navigate class="btn-accent" data-test="add-user-button">
                {{ __('Add user') }}
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card icon="users" color="blue" :label="__('Total users')" :value="number_format($this->summary['total'])" :note="__('Archived users not counted')" />
        <x-stat-card icon="check-badge" color="green" :label="__('Active')" :value="number_format($this->summary['active'])"
            :note="$this->summary['total'] > 0 ? __(':percent% of all users', ['percent' => intdiv($this->summary['active'] * 100, $this->summary['total'])]) : null" />
        <x-stat-card icon="no-symbol" color="zinc" :label="__('Inactive')" :value="number_format($this->summary['inactive'])" :note="__('Cannot log in')" />
        <x-stat-card icon="wrench-screwdriver" color="amber" :label="__('Workers vs admins')"
            :value="number_format($this->summary['roles']['worker']).' / '.number_format($this->summary['roles']['admin'])"
            :note="__(':count staff', ['count' => $this->summary['roles']['staff']])" />
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 md:grid-cols-[1fr_12rem_12rem]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search name, username or employee no.')"
                :aria-label="__('Search users')"
                clearable
            />

            <flux:select wire:model.live="role" :aria-label="__('Filter by role')">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($roles as $roleOption)
                    <flux:select.option :value="$roleOption">{{ __(ucfirst($roleOption)) }}</flux:select.option>
                @endforeach
            </flux:select>

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
                @if ($role !== '')
                    <x-filter-chip :label="__('Role: :value', ['value' => __(ucfirst($role))])" wire:click="$set('role', '')" />
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

    @php
        $roleColors = ['admin' => 'indigo', 'staff' => 'blue', 'worker' => 'amber'];
    @endphp

    <x-data-card>
        @if ($this->users->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="magnifying-glass" :title="__('No users match your search.')" :description="__('Try a different name or employee no., or clear the filters.')">
                    <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="users" :title="__('No users yet')" :description="__('Add the first worker or staff member so they can clock in.')">
                    <flux:button size="sm" icon="plus" :href="route('users.create')" wire:navigate class="btn-accent">{{ __('Add user') }}</flux:button>
                </x-empty-state>
            @endif
        @else
            {{-- Desktop / tablet: table --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Position') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Hourly rate') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->users as $user)
                            <flux:table.row :key="$user->id" class="h-16">
                                <flux:table.cell>
                                    <a href="{{ route('users.edit', $user) }}" wire:navigate class="group flex items-center gap-3">
                                        <x-avatar :name="$user->name" :initials="$user->initials()" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-brand-navy group-hover:text-brand-blue">{{ $user->name }}</span>
                                            <span class="block truncate text-xs text-zinc-500">{{ $user->username }}</span>
                                        </span>
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="rounded-md bg-neutral-soft px-2 py-0.5 font-mono text-xs text-neutral">{{ $user->employee_no }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-status-badge :color="$roleColors[$user->getRoleNames()->first()] ?? 'zinc'" :dot="false">{{ $user->roleLabel() }}</x-status-badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $user->position ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($user->hourly_rate !== "0.00")
                                        <span class="font-medium text-brand-navy">{{ \App\Support\Money::format($user->hourly_rate) }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @include('livewire.users.partials.status-badge', ['user' => $user])
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @include('livewire.users.partials.row-actions', ['user' => $user])
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: stacked cards --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->users as $user)
                    <li wire:key="user-card-{{ $user->id }}" class="flex items-start gap-3 p-4">
                        <x-avatar :name="$user->name" :initials="$user->initials()" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('users.edit', $user) }}" wire:navigate class="block truncate font-semibold text-brand-navy">{{ $user->name }}</a>
                            <p class="truncate text-xs text-zinc-500">{{ $user->username }} &middot; <span class="font-mono">{{ $user->employee_no }}</span></p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <x-status-badge :color="$roleColors[$user->getRoleNames()->first()] ?? 'zinc'" :dot="false">{{ $user->roleLabel() }}</x-status-badge>
                                @include('livewire.users.partials.status-badge', ['user' => $user])
                                @if ($user->hourly_rate !== "0.00")
                                    <span class="text-xs font-medium tabular-nums text-brand-navy">{{ \App\Support\Money::format($user->hourly_rate) }}/hr</span>
                                @endif
                            </div>
                        </div>
                        @include('livewire.users.partials.row-actions', ['user' => $user])
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->users" />
        </div>
    </x-data-card>
</div>
