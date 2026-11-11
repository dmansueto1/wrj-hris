{{-- System Settings (T-14.1), Admin only: one card per group, one row per setting. Not a table, so not paginated. --}}
<div class="flex flex-col gap-6">
    <x-page-header :title="__('System Settings')" :description="__('Rules for attendance, materials, sign-in and devices. Changes apply to new actions only.')">
        <x-slot:actions>
            <flux:modal.trigger name="restore-defaults">
                <flux:button icon="arrow-uturn-left" data-test="restore-defaults-button">{{ __('Restore defaults') }}</flux:button>
            </flux:modal.trigger>
            <flux:button
                icon="check"
                class="btn-accent"
                wire:click="save"
                :disabled="! $this->hasChanges"
                data-test="save-settings"
            >{{ __('Save changes') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @foreach ($groups as $group => $settings)
        <x-data-card data-test="settings-group-{{ \Illuminate\Support\Str::slug($group) }}">
            <h2 class="border-b border-zinc-100 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 sm:px-6">{{ __($group) }}</h2>

            <ul class="divide-y divide-zinc-100">
                @foreach ($settings as $setting)
                    @php($key = $setting['key'])
                    <li wire:key="setting-{{ $key }}" class="flex flex-col gap-3 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between md:gap-6" data-test="setting-{{ $key }}">
                        <div class="min-w-0 md:flex-1">
                            <p class="font-semibold text-brand-navy">{{ __($setting['label']) }}</p>
                            <p class="mt-0.5 text-sm text-zinc-500">{{ __($setting['description']) }}</p>
                            <p class="mt-1 text-xs text-zinc-400">{{ __('Default: :value', ['value' => \App\Support\SettingsCatalog::defaultLabel($setting)]) }}</p>
                        </div>

                        <div class="w-full md:w-56 md:shrink-0">
                            @if ($setting['type'] === 'bool')
                                <div class="flex md:justify-end">
                                    <flux:switch wire:model.live="values.{{ $key }}" :aria-label="__($setting['label'])" />
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <flux:input
                                        type="number"
                                        wire:model.live.debounce.300ms="values.{{ $key }}"
                                        :min="$setting['min']"
                                        :max="$setting['max']"
                                        :step="$setting['type'] === 'percent' ? '0.01' : '1'"
                                        inputmode="{{ $setting['type'] === 'percent' ? 'decimal' : 'numeric' }}"
                                        :aria-label="__($setting['label'])"
                                        class="min-w-0"
                                    />
                                    <span class="shrink-0 text-sm text-zinc-500">{{ __($setting['unit']) }}</span>
                                </div>
                            @endif
                            <flux:error name="values.{{ $key }}" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-data-card>
    @endforeach

    @if ($this->hasChanges)
        <div class="sticky bottom-4 z-20 rounded-card bg-white/95 px-4 py-3 shadow-card-hover ring-1 ring-brand-gold/40 backdrop-blur sm:px-6" data-test="unsaved-bar">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-medium text-brand-navy">{{ __('You have unsaved changes') }}</p>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="discard" data-test="discard-settings">{{ __('Discard') }}</flux:button>
                    <flux:button icon="check" class="btn-accent" wire:click="save">{{ __('Save changes') }}</flux:button>
                </div>
            </div>
        </div>
    @endif

    <flux:modal name="restore-defaults" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('Set every setting back to its default?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Unsaved changes are lost. Records already saved are not changed.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button class="btn-accent" wire:click="restoreDefaults" data-test="confirm-restore-defaults">{{ __('Restore defaults') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
