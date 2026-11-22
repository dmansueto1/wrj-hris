{{--
    One row per employee for one date (T-11.4): Morning / Afternoon In and Out, worked time, late (T-16.1), photos,
    remarks and Present / Absent / Rest Day, with stacked cards on phones. Used by the Daily attendance
    tab and the Dashboard's Today's Attendance (T-15.1).
    $rows: AttendanceHistoryService::daily() rows. $correctable: show the Correct menu (it also checks
    attendance.correct itself).
--}}
    {{-- Desktop / tablet: one row per employee; their attendance under Morning and Afternoon --}}
    <div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
        <flux:table>
            <flux:table.columns sticky class="bg-white">
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Morning In') }}</flux:table.column>
                <flux:table.column>{{ __('Morning Out') }}</flux:table.column>
                <flux:table.column>{{ __('Afternoon In') }}</flux:table.column>
                <flux:table.column>{{ __('Afternoon Out') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Worked time') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Late') }}</flux:table.column>
                <flux:table.column>{{ __('Photos') }}</flux:table.column>
                <flux:table.column class="w-32">{{ __('Remarks') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                @if ($correctable)
                    <flux:table.column align="end"><span>{{ __('Actions') }}</span></flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($rows as $row)
                    <flux:table.row :key="'daily-'.$row['user']->id" :data-test="'employee-row-'.$row['user']->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <x-avatar :name="$row['user']->name" :initials="$row['user']->initials()" size="sm" />
                                <span class="font-semibold text-brand-navy">{{ $row['user']->listName() }}</span>
                            </div>
                        </flux:table.cell>
                        @php($attendanceDay = \App\Support\AttendanceDay::of($row['attendances']))
                        @include('livewire.attendance.partials.day-cells', ['day' => $attendanceDay, 'photos' => true])
                        <flux:table.cell class="w-32">@include('livewire.attendance.partials.day-remarks', ['day' => $attendanceDay])</flux:table.cell>
                        <flux:table.cell>
                            @include('livewire.attendance.partials.presence-badge', ['present' => $row['present'], 'restDay' => $row['rest_day'], 'future' => $row['future']])
                        </flux:table.cell>
                        @if ($correctable)
                            <flux:table.cell align="end">
                                @include('livewire.attendance.partials.correct-menu', ['attendances' => $row['attendances'], 'key' => $row['user']->id])
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Phone: one card per employee --}}
    <ul class="divide-y divide-zinc-100 md:hidden">
        @foreach ($rows as $row)
            <li wire:key="daily-card-{{ $row['user']->id }}" class="flex flex-col gap-2 p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <x-avatar :name="$row['user']->name" :initials="$row['user']->initials()" size="sm" />
                        <span class="truncate font-semibold text-brand-navy">{{ $row['user']->listName() }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @include('livewire.attendance.partials.presence-badge', ['present' => $row['present'], 'restDay' => $row['rest_day'], 'future' => $row['future']])
                        @if ($correctable)
                            @include('livewire.attendance.partials.correct-menu', ['attendances' => $row['attendances'], 'key' => $row['user']->id])
                        @endif
                    </div>
                </div>
                @foreach ($row['attendances'] as $attendance)
                    @include('livewire.attendance.partials.attendance-line', ['attendance' => $attendance])
                @endforeach
                @include('livewire.attendance.partials.late-line', ['day' => \App\Support\AttendanceDay::of($row['attendances'])])
            </li>
        @endforeach
    </ul>
