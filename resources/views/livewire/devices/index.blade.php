<div class="flex flex-col gap-6">
    <x-page-header :title="__('Devices')" :description="__('Phones, tablets and computers allowed to use the kiosk and Clock In/Out.')" />

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card icon="clock" color="amber" :label="__('Pending requests')" :value="number_format($this->summary['pending'])" :note="__('Waiting for your approval')" />
        <x-stat-card icon="check-badge" color="green" :label="__('Approved')" :value="number_format($this->summary['approved'])" :note="__('Can use the kiosk and Clock In/Out')" />
        <x-stat-card icon="no-symbol" color="red" :label="__('Blocked')" :value="number_format($this->summary['blocked'])" :note="__('Cannot use either page')" />
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-card bg-white p-4 shadow-card ring-1 ring-brand-navy/5">
        <div class="grid gap-3 md:grid-cols-[1fr_12rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or code')" :aria-label="__('Search devices')" clearable />

            <flux:select wire:model.live="status" :aria-label="__('Filter by status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (\App\Models\Device::STATUSES as $value => [$label])
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($hasFilters)
            <div class="mt-3 flex flex-wrap items-center gap-2" data-test="active-filters">
                @if (trim($search) !== '')
                    <x-filter-chip :label="__('Search: :value', ['value' => $search])" wire:click="$set('search', '')" />
                @endif
                @if ($status !== '')
                    <x-filter-chip :label="__('Status: :value', ['value' => __(\App\Models\Device::STATUSES[$status][0] ?? $status)])" wire:click="$set('status', '')" />
                @endif
                <button type="button" wire:click="clearFilters" class="text-xs font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">
                    {{ __('Clear filters') }}
                </button>
            </div>
        @endif
    </div>

    <x-data-card>
        @if ($this->devices->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="magnifying-glass" :title="__('No devices match your search.')" :description="__('Try a different name or code, or clear the filters.')">
                    <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="device-tablet" :title="__('No devices yet')" :description="__('When someone taps Request access on the kiosk or Clock In/Out, the device appears here for you to approve.')" />
            @endif
        @else
            {{-- Desktop / tablet: table --}}
            <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns sticky class="bg-white">
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Device') }}</flux:table.column>
                        <flux:table.column>{{ __('Requested') }}</flux:table.column>
                        <flux:table.column>{{ __('Browser / OS') }}</flux:table.column>
                        <flux:table.column>{{ __('Last seen') }}</flux:table.column>
                        <flux:table.column>{{ __('Last user') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->devices as $device)
                            <flux:table.row :key="$device->id" data-test="device-row-{{ $device->id }}">
                                <flux:table.cell>
                                    @include('livewire.devices.partials.type', ['device' => $device])
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-brand-navy">{{ $device->name ?? __('Unnamed device') }}</span>
                                        @if ($device->id === $currentDeviceId)
                                            <x-status-badge color="blue" :dot="false">{{ __('This device') }}</x-status-badge>
                                        @endif
                                    </div>
                                    <span class="rounded-md bg-neutral-soft px-2 py-0.5 font-mono text-xs text-neutral">{{ $device->code }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="block text-sm text-brand-navy">{{ __($device->sourceLabel()) }}</span>
                                    <span class="text-xs text-zinc-500">
                                        {{ $device->requestedBy?->name ? __('by :name', ['name' => $device->requestedBy->name]).' · ' : '' }}{{ $device->requested_at->format('M j, g:i A') }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $device->description() }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">
                                    <span title="{{ $device->last_seen_at->format('M j, Y g:i A') }}">{{ $device->last_seen_at->diffForHumans() }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($device->lastUser)
                                        <div class="flex items-center gap-2">
                                            <x-avatar :name="$device->lastUser->name" :initials="$device->lastUser->initials()" size="sm" />
                                            <span class="text-sm">{{ $device->lastUser->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400">&mdash;</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-status-badge :color="$device->statusColor()">{{ __($device->statusLabel()) }}</x-status-badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @include('livewire.devices.partials.row-actions', ['device' => $device])
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone: stacked cards --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->devices as $device)
                    <li wire:key="device-card-{{ $device->id }}" class="flex items-start gap-3 p-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand-blue" title="{{ __($device->typeLabel()) }}" data-test="device-type-{{ $device->id }}">
                            <flux:icon :icon="$device->typeIcon()" class="size-5" />
                            <span class="sr-only">{{ __($device->typeLabel()) }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-brand-navy">{{ $device->name ?? __('Unnamed device') }}</p>
                            <p class="truncate text-xs text-zinc-500">
                                <span class="font-mono">{{ $device->code }}</span> &middot; {{ $device->description() }}
                            </p>
                            <p class="truncate text-xs text-zinc-500">
                                {{ __($device->sourceLabel()) }}{{ $device->requestedBy?->name ? ' · '.$device->requestedBy->name : '' }} &middot; {{ $device->last_seen_at->diffForHumans() }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <x-status-badge :color="$device->statusColor()">{{ __($device->statusLabel()) }}</x-status-badge>
                                @if ($device->id === $currentDeviceId)
                                    <x-status-badge color="blue" :dot="false">{{ __('This device') }}</x-status-badge>
                                @endif
                            </div>
                        </div>
                        @include('livewire.devices.partials.row-actions', ['device' => $device])
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->devices" />
        </div>
    </x-data-card>

    {{-- Approve / rename: name the device --}}
    <flux:modal name="device-name" class="w-full max-w-md">
        <form wire:submit="saveName" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $mode === 'approve' ? __('Approve device') : __('Rename device') }}</flux:heading>
                @if ($editing)
                    <flux:text class="mt-1">
                        {{ __('Code :code · :device', ['code' => $editing->code, 'device' => $editing->description()]) }}
                    </flux:text>
                @endif
            </div>

            <flux:input wire:model="deviceName" :label="__('Device name')" :placeholder="__('e.g. Site A tablet')" maxlength="100" data-test="device-name-input" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" class="btn-accent" data-test="save-device-name">
                    {{ $mode === 'approve' ? __('Approve') : __('Save name') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
