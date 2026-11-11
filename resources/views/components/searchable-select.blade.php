{{--
    A dropdown with a search box for long lists (e.g. employees), bound to the Livewire property
    `model` (its current `value` is passed in for the first render).
    Flux free has no searchable select. Options: list of ['value' => '12', 'label' => 'Abad, Ana',
    'hint' => 'EMP-001']; the search matches the label and the hint. `empty-label` adds a first
    option with value '' (e.g. "All employees"); `placeholder` shows while nothing is chosen.
--}}
@props([
    'model',
    'value' => '',
    'options' => [],
    'emptyLabel' => null,
    'placeholder' => __('Select'),
    'searchPlaceholder' => __('Search…'),
    'label' => null,
])

@php
    $choices = collect($options)->map(fn (array $option) => [
        'value' => (string) $option['value'],
        'label' => (string) $option['label'],
        'hint' => isset($option['hint']) ? (string) $option['hint'] : null,
    ]);

    if ($emptyLabel !== null) {
        $choices->prepend(['value' => '', 'label' => $emptyLabel, 'hint' => null]);
    }

    $currentLabel = $choices->firstWhere('value', (string) $value)['label'] ?? null;
    $listId = 'searchable-'.$model;
@endphp

<div
    {{ $attributes->class('relative') }}
    x-data="searchableSelect(@js($model), @js($choices->values()))"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="close(); $refs.button.focus()"
>
    <button
        type="button"
        x-ref="button"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="show()"
        class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white ps-3 pe-2 text-start text-base shadow-xs sm:text-sm"
        aria-haspopup="listbox"
        x-bind:aria-expanded="open"
        @if ($label) aria-label="{{ $label }}" @endif
    >
        <span class="truncate" x-bind:class="selectedLabel ? 'text-zinc-700' : 'text-zinc-400'" x-text="selectedLabel || @js($placeholder)">{{ $currentLabel ?? $placeholder }}</span>
        <flux:icon icon="chevron-up-down" variant="mini" class="size-5 shrink-0 text-zinc-400" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        class="absolute z-30 mt-1 w-full min-w-64 overflow-hidden rounded-lg bg-white shadow-card-hover ring-1 ring-brand-navy/10"
    >
        <div class="border-b border-zinc-100 p-2">
            <div class="relative">
                <flux:icon icon="magnifying-glass" variant="mini" class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                <input
                    type="search"
                    x-ref="search"
                    x-model="search"
                    x-on:input="active = 0"
                    x-on:keydown.down.prevent="move(1)"
                    x-on:keydown.up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose(filtered[active])"
                    x-on:keydown.tab="close()"
                    placeholder="{{ $searchPlaceholder }}"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="{{ $listId }}"
                    x-bind:aria-activedescendant="filtered[active] ? @js($listId) + '-' + active : null"
                    class="h-9 w-full rounded-md border border-zinc-200 bg-white ps-8 pe-2 text-base text-zinc-700 placeholder-zinc-400 focus:border-brand-blue focus:outline-none sm:text-sm"
                />
            </div>
        </div>

        <ul id="{{ $listId }}" x-ref="list" role="listbox" class="max-h-64 overflow-y-auto py-1" @if ($label) aria-label="{{ $label }}" @endif>
            <template x-for="(option, index) in filtered" x-bind:key="option.value">
                <li
                    role="option"
                    x-bind:id="@js($listId) + '-' + index"
                    x-bind:aria-selected="option.value === selected"
                    x-bind:data-active="index === active"
                    x-on:click="choose(option)"
                    x-on:mousemove="active = index"
                    class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm"
                    x-bind:class="index === active ? 'bg-brand-tint text-brand-navy' : 'text-zinc-700'"
                >
                    <span class="truncate" x-bind:class="option.value === selected && 'font-semibold'" x-text="option.label"></span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="text-xs text-zinc-500" x-show="option.hint" x-text="option.hint"></span>
                        <flux:icon icon="check" variant="mini" class="size-4 text-brand-blue" x-show="option.value === selected" />
                    </span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-500">{{ __('No matches found.') }}</li>
        </ul>
    </div>
</div>
