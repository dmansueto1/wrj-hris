{{-- Today's 10 newest clock events, so each worker can confirm their own entry. No money or employee numbers. --}}
<section wire:poll.10s class="flex flex-col overflow-hidden rounded-card bg-white shadow-card-hover ring-1 ring-brand-navy/5 lg:h-full" aria-labelledby="kiosk-duties-heading">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
        <div>
            <h2 id="kiosk-duties-heading" class="flex items-center gap-2 text-base font-bold text-brand-navy">
                <span class="relative flex size-2.5" aria-hidden="true">
                    <span class="absolute inline-flex size-full rounded-full bg-danger opacity-60 motion-safe:animate-ping"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-danger"></span>
                </span>
                {{ __("Today's attendance") }}
            </h2>
            <p class="text-xs text-zinc-500">{{ __('Live 10 latest attendance records') }}</p>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success" data-test="kiosk-on-duty-count">
            <flux:icon icon="users" class="size-4" />
            {{ trans_choice(':count clocked in now|:count clocked in now', $this->onDutyCount, ['count' => $this->onDutyCount]) }}
        </span>
    </div>

    @if ($this->duties->isEmpty())
        <x-empty-state class="min-h-0 flex-1" icon="clock" :title="__('No one has clocked in yet today.')" :description="__('Clock in on the left, and your name appears here.')" />
    @else
        <ol class="max-h-96 min-h-0 flex-1 divide-y divide-zinc-100 overflow-y-auto px-3 py-1 sm:px-4 lg:max-h-none">
            @foreach ($this->duties as $duty)
                <li
                    wire:key="kiosk-duty-{{ $duty['key'] }}"
                    @if ($loop->first)
                        x-data="{ fresh: true }"
                        x-init="setTimeout(() => fresh = false, 6000)"
                        x-bind:class="fresh && 'bg-warning-soft'"
                    @endif
                    class="animate-row-in flex items-center gap-3 rounded-lg px-2 py-2 transition-colors duration-500"
                >
                    <x-avatar :name="$duty['name']" :initials="$duty['initials']" size="sm" />
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="truncate text-sm font-semibold text-brand-navy">{{ $duty['name'] }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $duty['project'] }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-status-badge :color="$duty['direction'] === 'in' ? 'green' : 'zinc'">{{ $duty['type'] }}</x-status-badge>
                        <span class="w-16 text-end text-sm font-semibold tabular-nums text-brand-navy">{{ $duty['time'] }}</span>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    <p class="shrink-0 border-t border-zinc-100 px-5 py-3 text-xs text-zinc-400 sm:px-6">
        {{ __('Latest :count · updates automatically', ['count' => \App\Livewire\Attendance\KioskDuties::ROWS]) }}
    </p>
</section>
