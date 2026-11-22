{{-- Dashboard (T-15.1): a greeting by name, then the admin overview (dashboard.overview) or the user's own attendance. The only page with stat cards. --}}
<div class="flex flex-col gap-6" x-data="{ photo: { src: '', label: '' } }">
    {{-- Greeting by name and time of day (Asia/Manila), instead of a plain "Dashboard" title --}}
    <x-page-header
        :title="\App\Support\Greeting::to(auth()->user()->first_name, now())"
        :description="\App\Support\Greeting::subtitle($this->isOverview, ! $this->isOverview && $this->openAttendance !== null, now())"
        data-test="dashboard-greeting"
    >
        <x-slot:badge>
            <span class="flex size-9 items-center justify-center rounded-full bg-warning-soft text-brand-gold" aria-hidden="true">
                <flux:icon :icon="\App\Support\Greeting::icon(now())" class="size-5" />
            </span>
        </x-slot:badge>

        @if ($this->isOverview)
            {{-- Admin only: the shared-tablet kiosk in a new tab; the Admin stays signed in here. --}}
            <x-slot:actions>
                <flux:button
                    icon="computer-desktop"
                    icon:trailing="arrow-top-right-on-square"
                    :href="route('kiosk')"
                    target="_blank"
                    rel="noopener"
                    :title="__('Opens in a new tab')"
                    data-test="open-kiosk"
                >{{ __('Open kiosk') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($this->isOverview)
        @php($stats = $this->overview)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                icon="play-circle"
                color="green"
                :label="__('Clocked in now')"
                :value="number_format($stats['clocked_in'])"
                :note="__('Working right now')"
                :href="route('attendance.index')"
                data-test="card-clocked-in"
            />
            <x-stat-card
                icon="check-circle"
                color="blue"
                :label="__('Present today')"
                :value="__(':present of :employees', ['present' => number_format($stats['present']), 'employees' => number_format($stats['employees'])])"
                :note="__('Employees with attendance today')"
                :href="route('attendance.index')"
                data-test="card-present"
            />
            <x-stat-card
                icon="exclamation-triangle"
                :color="$stats['needs_correction'] > 0 ? 'amber' : 'zinc'"
                :label="__('Needs correction')"
                :value="number_format($stats['needs_correction'])"
                :note="__('Not clocked out')"
                :href="route('attendance.index', ['needs_correction' => 1])"
                data-test="card-needs-correction"
            />
            <x-stat-card
                icon="device-tablet"
                :color="$stats['pending_devices'] > 0 ? 'amber' : 'zinc'"
                :label="__('Pending device requests')"
                :value="number_format($stats['pending_devices'])"
                :note="__('Waiting for your approval')"
                :href="route('devices.index', ['status' => 'pending'])"
                data-test="card-pending-devices"
            />
        </div>

        {{-- Expenses (T-16): this month across all projects, and the projects running out of budget --}}
        @php($expenses = $this->expenses)
        @php($money = fn ($amount) => \App\Support\Money::format($amount))
        <section class="flex flex-col gap-3" aria-labelledby="dashboard-expenses">
            <h2 id="dashboard-expenses" class="text-xs font-semibold uppercase tracking-wider text-neutral">{{ __('Expenses') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-stat-card
                    icon="calculator"
                    color="blue"
                    :label="__('Expenses this month')"
                    :value="$money($expenses['month']['running'])"
                    :note="__('Labor :labor · Materials :materials', ['labor' => $money($expenses['month']['labor']), 'materials' => $money($expenses['month']['materials'])])"
                    :href="route('projects.index')"
                    data-test="card-expenses-month"
                />
                <x-stat-card
                    icon="chart-pie"
                    :color="$expenses['budget']['over'] > 0 ? 'red' : ($expenses['budget']['warning'] > 0 ? 'amber' : 'zinc')"
                    :label="__('Projects near budget')"
                    :value="number_format($expenses['budget']['warning'])"
                    :note="$expenses['budget']['over'] > 0 ? __(':count over budget', ['count' => number_format($expenses['budget']['over'])]) : __('At 80% or more of budget')"
                    :href="route('projects.index', ['budget' => 'watch'])"
                    data-test="card-budget-watch"
                />
            </div>
        </section>

        <x-data-card data-test="todays-attendance">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 sm:px-6">
                <h2 class="text-base font-bold text-brand-navy">{{ __("Today's Attendance") }}</h2>
                <a href="{{ route('attendance.index') }}" wire:navigate class="text-sm font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">{{ __('Open All Attendance') }}</a>
            </div>

            @if ($this->todaysAttendance->isEmpty())
                <x-empty-state icon="users" :title="__('No employees yet.')" :description="__('Employees show here once they are added on the Users page.')" />
            @else
                @include('livewire.attendance.partials.daily-table', ['rows' => $this->todaysAttendance, 'correctable' => false])
            @endif

            <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
                <x-table-footer :paginator="$this->todaysAttendance" />
            </div>
        </x-data-card>

        {{-- One modal for every thumbnail: the clicked photo full size --}}
        <flux:modal name="attendance-photo" class="w-full max-w-lg">
            <div class="flex flex-col gap-4">
                <h2 class="pe-8 text-base font-bold text-brand-navy" x-text="photo.label"></h2>
                <img x-bind:src="photo.src" x-bind:alt="photo.label" class="max-h-[70vh] w-full rounded-xl bg-zinc-100 object-contain" />
            </div>
        </flux:modal>
    @else
        @include('livewire.attendance.partials.flagged-notice', ['flagged' => $this->flagged, 'test' => 'flagged-notice'])

        @can('attendance.clock')
            {{-- Clock shortcut: one big button to the Clock In/Out page --}}
            <section class="flex flex-col gap-4 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 sm:flex-row sm:items-center sm:justify-between sm:p-6" data-test="dashboard-clock">
                @if ($this->openAttendance)
                    <div class="min-w-0">
                        <x-status-badge color="green">{{ __('Clocked In') }}</x-status-badge>
                        <p class="mt-2 font-semibold text-brand-navy">
                            {{ __('Clocked In since :time', ['time' => $this->openAttendance->clock_in_at->format('g:i A')]) }}
                            &middot; {{ $this->openAttendance->project->name }}
                        </p>
                    </div>
                    <a
                        href="{{ route('clock') }}"
                        wire:navigate
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-neutral px-6 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-neutral/90 sm:w-auto"
                        data-test="dashboard-clock-out"
                    >
                        <flux:icon icon="stop-circle" class="size-5" /> {{ __('Clock Out') }}
                    </a>
                @else
                    <div class="min-w-0">
                        <x-status-badge color="zinc">{{ __('Not clocked in') }}</x-status-badge>
                        <p class="mt-2 text-sm text-zinc-600">{{ __('Pick your project and clock in when you start work.') }}</p>
                    </div>
                    <a
                        href="{{ route('clock') }}"
                        wire:navigate
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-success px-6 font-semibold text-white shadow-card transition-colors duration-150 hover:bg-success/90 sm:w-auto"
                        data-test="dashboard-clock-in"
                    >
                        <flux:icon icon="play-circle" class="size-5" /> {{ __('Clock In') }}
                    </a>
                @endif
            </section>
        @endcan

        <x-data-card data-test="my-days">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 sm:px-6">
                <h2 class="text-base font-bold text-brand-navy">{{ __('My Attendance') }}</h2>
                <a href="{{ route('attendance.mine') }}" wire:navigate class="text-sm font-medium text-brand-blue hover:text-brand-blue-dark hover:underline">{{ __('See all in My Attendance') }}</a>
            </div>

            @include('livewire.attendance.partials.my-days-table', ['days' => $this->myDays, 'totalHours' => null, 'totalLate' => null])

            <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
                <x-table-footer :paginator="$this->myDays" />
            </div>
        </x-data-card>
    @endif
</div>
