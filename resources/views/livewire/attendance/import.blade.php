{{-- Import Attendance tab (T-12.1), Admin only: template, upload with password, rows not imported and recent imports. --}}
<div class="flex flex-col gap-6">
    <div class="grid gap-4 lg:grid-cols-5">
        {{-- 1. Template --}}
        <div class="flex flex-col gap-4 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 sm:p-6 lg:col-span-2">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-tint text-sm font-bold text-brand-navy">1</span>
                <div>
                    <h2 class="font-bold text-brand-navy">{{ __('Download the template') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600">{{ __('When the system is down, workers write their clock-in and clock-out on this sheet. It lists the employee numbers and Ongoing project codes to copy from.') }}</p>
                </div>
            </div>
            <div>
                <flux:button icon="arrow-down-tray" wire:click="downloadTemplate" data-test="download-template">{{ __('Download template') }}</flux:button>
            </div>
        </div>

        {{-- 2. Upload --}}
        <form wire:submit="import" class="flex flex-col gap-4 rounded-card bg-white p-5 shadow-card ring-1 ring-brand-navy/5 sm:p-6 lg:col-span-3" data-test="import-form">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-tint text-sm font-bold text-brand-navy">2</span>
                <div>
                    <h2 class="font-bold text-brand-navy">{{ __('Upload the filled-in file') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600">{{ __('Each valid row becomes a Clocked Out attendance, costed at the rate on its date. Rows with a problem are listed below.') }}</p>
                </div>
            </div>

            <flux:input
                type="file"
                wire:model="file"
                :label="__('Attendance file')"
                :description="__('.xlsx, .xls or .csv, up to 2 MB and :max rows. Edited in Numbers on a Mac? Choose File → Export To → Excel first.', ['max' => number_format(\App\Services\AttendanceImportService::MAX_ROWS)])"
                accept=".xlsx,.xls,.csv"
                data-test="import-file"
            />

            <flux:input
                type="password"
                wire:model="password"
                :label="__('Your password')"
                :description="__('Imported attendance becomes labor cost, so every import is confirmed with your password.')"
                autocomplete="current-password"
                viewable
                data-test="import-password"
            />

            <div class="flex justify-end">
                <flux:button type="submit" icon="arrow-up-tray" class="btn-accent" wire:loading.attr="disabled" wire:target="import,file" data-test="import-submit">
                    {{ __('Import') }}
                </flux:button>
            </div>
        </form>
    </div>

    {{-- Result of the latest (or chosen) import --}}
    @if ($result = $this->result)
        <div class="flex flex-col gap-4" data-test="import-result">
            <p class="text-sm text-zinc-600">
                <span class="font-semibold text-brand-navy">{{ $result->file_name }}</span>
                &middot; {{ $result->created_at->format('M j, Y g:i A') }}
                &middot; {{ $result->importedBy?->name }}
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card icon="document-text" color="blue" :label="__('Rows read')" :value="number_format($result->rows_total)" :note="__('Blank and example rows skipped')" />
                <x-stat-card icon="check-circle" color="green" :label="__('Imported')" :value="number_format($result->rows_imported)" :note="__('Clocked Out attendance records')" />
                <x-stat-card icon="x-circle" color="red" :label="__('Not imported')" :value="number_format($result->rows_failed)" :note="__('Listed below with the reason')" />
            </div>

            @if ($failures = $this->failures)
                <x-data-card>
                    <div class="border-b border-zinc-100 px-4 py-3 sm:px-6">
                        <h3 class="font-bold text-brand-navy">{{ __('Rows not imported') }}</h3>
                        <p class="text-sm text-zinc-600">{{ __('Fix these rows in the file, delete the rows that were imported, and upload it again.') }}</p>
                    </div>

                    {{-- Desktop / tablet --}}
                    <div class="hidden px-4 sm:px-6 md:block">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="w-20">{{ __('Row') }}</flux:table.column>
                                <flux:table.column class="w-40">{{ __('Employee No.') }}</flux:table.column>
                                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($failures as $failure)
                                    <flux:table.row :key="'failure-'.$failure['row']" :data-test="'failure-row-'.$failure['row']">
                                        <flux:table.cell class="tabular-nums font-semibold text-brand-navy">{{ $failure['row'] }}</flux:table.cell>
                                        <flux:table.cell><span class="font-mono text-xs">{{ $failure['employee_no'] ?: '—' }}</span></flux:table.cell>
                                        <flux:table.cell class="text-danger">{{ $failure['reason'] }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    {{-- Phone --}}
                    <ul class="divide-y divide-zinc-100 md:hidden">
                        @foreach ($failures as $failure)
                            <li wire:key="failure-card-{{ $failure['row'] }}" class="flex flex-col gap-1 p-4 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-brand-navy">{{ __('Row :row', ['row' => $failure['row']]) }}</span>
                                    <span class="font-mono text-xs text-zinc-600">{{ $failure['employee_no'] ?: '—' }}</span>
                                </div>
                                <p class="text-danger">{{ $failure['reason'] }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
                        <x-table-footer :paginator="$failures" />
                    </div>
                </x-data-card>
            @endif
        </div>
    @endif

    {{-- Recent imports --}}
    <x-data-card>
        <div class="border-b border-zinc-100 px-4 py-3 sm:px-6">
            <h3 class="font-bold text-brand-navy">{{ __('Recent imports') }}</h3>
        </div>

        @if ($this->imports->isEmpty())
            <x-empty-state icon="arrow-up-tray" :title="__('No imports yet.')" :description="__('Uploaded attendance files show here with who imported them.')" />
        @else
            {{-- Desktop / tablet --}}
            <div class="hidden px-4 sm:px-6 md:block">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Imported') }}</flux:table.column>
                        <flux:table.column>{{ __('File') }}</flux:table.column>
                        <flux:table.column>{{ __('Imported by') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Imported rows') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Not imported') }}</flux:table.column>
                        <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->imports as $import)
                            <flux:table.row :key="'import-'.$import->id" :data-test="'import-row-'.$import->id">
                                <flux:table.cell class="whitespace-nowrap">{{ $import->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                                <flux:table.cell class="max-w-56 truncate">{{ $import->file_name }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <x-avatar :name="$import->importedBy?->name ?? '?'" :initials="$import->importedBy?->initials()" size="sm" />
                                        <span>{{ $import->importedBy?->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($import->rows_imported) }}</flux:table.cell>
                                <flux:table.cell align="end" @class(['tabular-nums', 'font-semibold text-danger' => $import->rows_failed > 0])>{{ number_format($import->rows_failed) }}</flux:table.cell>
                                <flux:table.cell align="end">@include('livewire.attendance.partials.import-actions', ['import' => $import])</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Phone --}}
            <ul class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->imports as $import)
                    <li wire:key="import-card-{{ $import->id }}" class="flex items-start gap-3 p-4">
                        <x-avatar :name="$import->importedBy?->name ?? '?'" :initials="$import->importedBy?->initials()" size="sm" />
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="truncate font-semibold text-brand-navy">{{ $import->file_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $import->created_at->format('M j, Y g:i A') }} &middot; {{ $import->importedBy?->name }}</p>
                            <p class="mt-1 tabular-nums">
                                {{ __(':count imported', ['count' => number_format($import->rows_imported)]) }}
                                @if ($import->rows_failed > 0)
                                    &middot; <span class="font-semibold text-danger">{{ __(':count not imported', ['count' => number_format($import->rows_failed)]) }}</span>
                                @endif
                            </p>
                        </div>
                        @include('livewire.attendance.partials.import-actions', ['import' => $import])
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="border-t border-zinc-100 px-4 pb-3 sm:px-6">
            <x-table-footer :paginator="$this->imports" />
        </div>
    </x-data-card>
</div>
