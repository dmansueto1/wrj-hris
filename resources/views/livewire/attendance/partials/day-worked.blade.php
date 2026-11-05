{{-- A day's Worked time: the counted hours of its clocked-out attendance ("8.00 h") and the live counter of the one still Clocked In. Notes go in Remarks. --}}
@php($hours = $day->hours())
@php($open = $day->open())

@if ($hours === null && $open === null)
    <span class="text-zinc-400">&mdash;</span>
@else
    <span class="inline-flex flex-col items-end whitespace-nowrap">
        @if ($hours !== null)
            <span data-test="worked-hours">{{ $hours }} h</span>
        @endif
        @if ($open)
            @include('livewire.attendance.partials.worked-time', ['attendance' => $open])
        @endif
    </span>
@endif
