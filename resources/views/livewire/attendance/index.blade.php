{{-- All Attendance (T-11, T-11.4, T-12.1): Daily attendance and By employee tabs for Admin and Staff, Import Attendance for Admin. --}}
<div class="flex flex-col gap-6" x-data="{ photo: { src: '', label: '' } }">
    <x-page-header :title="__('All Attendance')" :description="__('Who is present or absent each day, and each employee\'s attendance over time.')" />

    {{-- Tabs (Flux free has no tabs component) --}}
    <div class="flex gap-1 overflow-x-auto rounded-card bg-white p-1 shadow-card ring-1 ring-brand-navy/5" role="tablist" aria-label="{{ __('All Attendance views') }}">
        @php($tabs = ['daily' => [__('Daily attendance'), 'calendar-days'], 'employee' => [__('By employee'), 'user']])
        @can('attendance.correct')
            @php($tabs['import'] = [__('Import Attendance'), 'arrow-up-tray'])
        @endcan
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
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'import')
        <livewire:attendance.import />
    @elseif ($tab === 'employee')
        @include('livewire.attendance.partials.employee-tab')
    @else
        @include('livewire.attendance.partials.daily-tab')
    @endif

    {{-- One correction modal for every row (T-12, Admin only) --}}
    @can('attendance.correct')
        <livewire:attendance.correction />
    @endcan

    {{-- One modal for every thumbnail: the clicked photo full size --}}
    <flux:modal name="attendance-photo" class="w-full max-w-lg">
        <div class="flex flex-col gap-4">
            <h2 class="pe-8 text-base font-bold text-brand-navy" x-text="photo.label"></h2>
            <img x-bind:src="photo.src" x-bind:alt="photo.label" class="max-h-[70vh] w-full rounded-xl bg-zinc-100 object-contain" />
        </div>
    </flux:modal>
</div>
