{{--
    "Correct" menu of one row (T-12, Admin only): one item per attendance in the row, each opening the
    correction modal, plus "Compare with original" for records created by a correction.
--}}
@can('attendance.correct')
    @if (collect($attendances)->isNotEmpty())
        <flux:dropdown position="bottom" align="end">
            <flux:button size="sm" variant="ghost" icon="pencil-square" :aria-label="__('Correct')" data-test="correct-{{ $key }}"></flux:button>

            <flux:menu>
                @foreach ($attendances as $attendance)
                    <flux:menu.item
                        wire:click="$dispatch('correct-attendance', { id: {{ $attendance->id }} })"
                        data-test="correct-attendance-{{ $attendance->id }}"
                    >
                        <span class="tabular-nums">{{ $attendance->clock_in_at->format('g:i A') }} &ndash; {{ $attendance->clock_out_at?->format('g:i A') ?? __('no clock-out') }}</span>
                        <span class="ms-1 truncate text-zinc-500">&middot; {{ $attendance->project->name }}</span>
                    </flux:menu.item>
                @endforeach

                @php($corrected = collect($attendances)->filter->isCorrection())
                @if ($corrected->isNotEmpty())
                    <flux:menu.separator />
                    @foreach ($corrected as $attendance)
                        <flux:menu.item
                            icon="arrows-right-left"
                            wire:click="$dispatch('compare-attendance', { id: {{ $attendance->id }} })"
                            data-test="compare-attendance-{{ $attendance->id }}"
                        >
                            {{ __('Compare with original') }}
                            @if (collect($attendances)->count() > 1)
                                <span class="ms-1 tabular-nums text-zinc-500">&middot; {{ $attendance->clock_in_at->format('g:i A') }}</span>
                            @endif
                        </flux:menu.item>
                    @endforeach
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif
@endcan
