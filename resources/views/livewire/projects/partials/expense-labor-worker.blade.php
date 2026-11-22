{{-- Project page (T-16), Labor by worker: ProjectExpenseService::laborByWorker() rows. --}}
<div class="hidden max-h-[70vh] overflow-auto px-4 sm:px-6 md:block">
    <flux:table>
        <flux:table.columns sticky class="bg-white">
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Attendance') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Hours') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Labor cost') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($rows as $row)
                <flux:table.row :key="'worker-'.$row['user']->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$row['user']->name" size="sm" />
                            <div class="min-w-0">
                                <span class="block font-semibold text-brand-navy">{{ $row['user']->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $row['user']->position ?? $row['user']->employee_no }}</span>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($row['attendances']) }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $row['hours'] }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold text-brand-navy">{{ $money($row['cost']) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>

<ul class="divide-y divide-zinc-100 md:hidden">
    @foreach ($rows as $row)
        <li wire:key="worker-card-{{ $row['user']->id }}" class="flex items-center gap-3 p-4">
            <x-avatar :name="$row['user']->name" size="sm" />
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-brand-navy">{{ $row['user']->name }}</p>
                <p class="text-sm tabular-nums text-zinc-500">{{ $row['hours'] }} h · {{ trans_choice(':count attendance|:count attendances', $row['attendances'], ['count' => number_format($row['attendances'])]) }}</p>
            </div>
            <span class="font-bold tabular-nums text-brand-navy">{{ $money($row['cost']) }}</span>
        </li>
    @endforeach
</ul>
