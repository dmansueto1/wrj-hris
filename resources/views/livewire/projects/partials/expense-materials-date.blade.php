{{-- Project page (T-16), Materials by date: ProjectExpenseService::materialsByDate() rows, newest first. --}}
<div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
    <flux:table>
        <flux:table.columns sticky class="bg-white">
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Entries') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Total cost') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($rows as $row)
                <flux:table.row :key="'materials-date-'.$row['date']->toDateString()">
                    <flux:table.cell class="whitespace-nowrap font-semibold text-brand-navy">{{ $row['date']->format('D, M j, Y') }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($row['entries']) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold text-brand-navy">{{ $money($row['total']) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>

<ul class="divide-y divide-zinc-100 md:hidden">
    @foreach ($rows as $row)
        <li wire:key="materials-date-card-{{ $row['date']->toDateString() }}" class="flex items-center justify-between gap-3 p-4">
            <div class="min-w-0">
                <p class="font-semibold text-brand-navy">{{ $row['date']->format('D, M j, Y') }}</p>
                <p class="text-sm tabular-nums text-zinc-500">{{ trans_choice(':count entry|:count entries', $row['entries'], ['count' => number_format($row['entries'])]) }}</p>
            </div>
            <span class="font-bold tabular-nums text-brand-navy">{{ $money($row['total']) }}</span>
        </li>
    @endforeach
</ul>
