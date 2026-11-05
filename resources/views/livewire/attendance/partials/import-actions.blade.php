{{-- "Recent imports" row menu (T-12.1): show that import's result and its rows not imported. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :file', ['file' => $import->file_name])" data-test="import-actions-{{ $import->id }}" />

    <flux:menu>
        <flux:menu.item icon="list-bullet" wire:click="showFailures({{ $import->id }})" :disabled="$import->rows_failed === 0">
            {{ $import->rows_failed > 0 ? __('Show rows not imported') : __('All rows were imported') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
