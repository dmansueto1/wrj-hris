{{-- Under every table: "Rows per page" on the left, page links on the right.
     The Livewire component must use App\Livewire\Concerns\WithPerPage. --}}
@props(['paginator'])

<div {{ $attributes->class('flex flex-col gap-3 pt-3 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex items-center gap-2 text-sm text-zinc-600">
        <label for="per-page-{{ $paginator->getPageName() }}" class="whitespace-nowrap">{{ __('Rows per page') }}</label>
        <flux:select
            id="per-page-{{ $paginator->getPageName() }}"
            wire:model.live="perPage"
            size="sm"
            class="w-20"
            data-test="per-page-select"
        >
            @foreach (\App\Support\Pagination::PER_PAGE_OPTIONS as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:pagination :paginator="$paginator" class="flex-1 border-t-0 pt-0 sm:justify-end" />
</div>
