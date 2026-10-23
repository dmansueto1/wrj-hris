{{--
    Clock-in and clock-out photo thumbnails (T-11), served only by the permission-checked attendance.photo route.
    A Clocked In record shows only its clock-in photo. Clicking one opens it full size in the "attendance-photo" modal.
--}}
@php
    $photos = array_filter([
        'in' => $attendance->clock_in_photo_path,
        'out' => $attendance->clock_out_at ? $attendance->clock_out_photo_path : null,
    ]);
@endphp

@if ($photos === [])
    <span class="text-xs text-zinc-400">{{ __('No photo') }}</span>
@else
    <div class="flex items-center -space-x-2">
        @foreach ($photos as $type => $path)
            @php
                $url = route('attendance.photo', [$attendance, $type]);
                $label = $type === 'in'
                    ? __('Clock In photo of :name, :time', ['name' => $attendance->user?->name, 'time' => $attendance->clock_in_at->format('M j, g:i A')])
                    : __('Clock Out photo of :name, :time', ['name' => $attendance->user?->name, 'time' => $attendance->clock_out_at?->format('M j, g:i A')]);
            @endphp
            <button
                type="button"
                class="rounded-full ring-2 ring-white transition-transform duration-150 hover:z-10 hover:scale-110 focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-brand-blue"
                x-on:click="photo = { src: @js($url), label: @js($label) }; $flux.modal('attendance-photo').show()"
                title="{{ $label }}"
                data-test="photo-{{ $type }}-{{ $attendance->id }}"
            >
                <img src="{{ $url }}" alt="{{ $label }}" loading="lazy" class="size-7 rounded-full bg-zinc-100 object-cover" />
            </button>
        @endforeach
    </div>
@endif
