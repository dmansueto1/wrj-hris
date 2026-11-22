{{-- Project page (T-16), Materials by item: ProjectExpenseService::materialsByItem() rows; totals include the markup. --}}
<div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
    <flux:table>
        <flux:table.columns sticky class="bg-white">
            <flux:table.column>{{ __('Material') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Entries') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
            <flux:table.column>{{ __('Unit') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Total cost') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($rows as $row)
                <flux:table.row :key="'material-'.$row['material']->id">
                    <flux:table.cell>
                        <span class="block font-semibold text-brand-navy">{{ $row['material']->name }}</span>
                        <span class="font-mono text-xs text-zinc-500">{{ $row['material']->code }}</span>
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($row['entries']) }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $row['quantity'] }}</flux:table.cell>
                    <flux:table.cell>{{ $row['material']->unit }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold text-brand-navy">{{ $money($row['total']) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>

<ul class="divide-y divide-zinc-100 md:hidden">
    @foreach ($rows as $row)
        <li wire:key="material-card-{{ $row['material']->id }}" class="flex items-center justify-between gap-3 p-4">
            <div class="min-w-0">
                <p class="truncate font-semibold text-brand-navy">{{ $row['material']->name }}</p>
                <p class="text-sm tabular-nums text-zinc-500">{{ $row['quantity'] }} {{ $row['material']->unit }}</p>
            </div>
            <span class="font-bold tabular-nums text-brand-navy">{{ $money($row['total']) }}</span>
        </li>
    @endforeach
</ul>
