{{--
    Morning In, Morning Out, Afternoon In, Afternoon Out, Worked time (and Photos when $photos) cells of one
    person's day on the attendance tables, one line each. The project shows when hovering a time.
    A record running through lunch (or a one-shift day) fills Morning In and Afternoon Out.
--}}
@foreach ($day->columns() as $column => $entries)
    <flux:table.cell class="whitespace-nowrap tabular-nums" data-test="{{ str_replace('_', '-', $column) }}">
        @forelse ($entries as $entry)
            <span class="block" title="{{ $entry['attendance']->project->name }}">
                @if ($entry['part'] === 'in')
                    {{ $entry['attendance']->clock_in_at->format('g:i A') }}
                @else
                    @include('livewire.attendance.partials.clock-out-time', ['attendance' => $entry['attendance']])
                @endif
            </span>
        @empty
            <span class="text-zinc-400">&mdash;</span>
        @endforelse
    </flux:table.cell>
@endforeach

<flux:table.cell align="end" class="tabular-nums">
    @include('livewire.attendance.partials.day-worked', ['day' => $day])
</flux:table.cell>

@if ($photos)
    <flux:table.cell>
        @php($withPhotos = $day->all()->filter(fn ($attendance) => $attendance->clock_in_photo_path || $attendance->clock_out_photo_path))
        @if ($day->isEmpty())
            <span class="text-zinc-400">&mdash;</span>
        @elseif ($withPhotos->isEmpty())
            <span class="text-xs text-zinc-400">{{ __('No photo') }}</span>
        @else
            <div class="flex flex-nowrap items-center gap-1 whitespace-nowrap">
                @foreach ($withPhotos as $attendance)
                    @include('livewire.attendance.partials.photo-thumbs', ['attendance' => $attendance])
                @endforeach
            </div>
        @endif
    </flux:table.cell>
@endif
