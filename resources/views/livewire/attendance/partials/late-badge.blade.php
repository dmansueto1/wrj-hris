{{--
    Late (T-16.1), the same everywhere: an amber badge with the hours ("0.42 h"); a zero total
    ("0.00") is plain gray text. Used by the day cells, phone cards and Total rows.
    $hours: "0.42". $label: also show the word "Late" (phone cards). $title: hover text. $test: data-test.
--}}
@php($text = ($label ?? false) ? __('Late :hours h', ['hours' => $hours]) : $hours.' h')

@if ($hours === '0.00')
    <span class="whitespace-nowrap tabular-nums text-zinc-400" @isset($test) data-test="{{ $test }}" @endisset>{{ $text }}</span>
@else
    <x-status-badge color="amber" class="tabular-nums font-semibold" :title="$title ?? null" :data-test="$test ?? null">{{ $text }}</x-status-badge>
@endif
