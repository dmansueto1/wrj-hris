<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Projects') }}</flux:heading>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('projects.create')" wire:navigate data-test="add-project-button">
                {{ __('Add project') }}
            </flux:button>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search code or name')"
            :aria-label="__('Search projects')"
            class="sm:col-span-2"
        />

        <flux:select wire:model.live="status" :aria-label="__('Filter by status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $value => [$label])
                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white px-4 py-2 sm:px-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Start') }}</flux:table.column>
                <flux:table.column>{{ __('End') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Budget') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                @if ($canManage)
                    <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->projects as $project)
                    <flux:table.row :key="$project->id">
                        <flux:table.cell>
                            <flux:link :href="route('projects.show', $project)" wire:navigate>{{ $project->code }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="hover:underline">{{ $project->name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $project->location }}</flux:table.cell>
                        <flux:table.cell>{{ $project->client_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $project->start_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $project->end_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $project->budget !== null ? \App\Support\Money::format($project->budget) : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->statusColor()">{{ __($project->statusLabel()) }}</flux:badge>
                        </flux:table.cell>
                        @if ($canManage)
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil-square"
                                        :href="route('projects.edit', $project)"
                                        wire:navigate
                                        :aria-label="__('Edit :code', ['code' => $project->code])"
                                        data-test="edit-project-{{ $project->id }}"
                                    />
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="archive-box"
                                        wire:click="archive({{ $project->id }})"
                                        wire:confirm="{{ __('Archive :code? Its attendance and material records are kept.', ['code' => $project->code]) }}"
                                        :aria-label="__('Archive :code', ['code' => $project->code])"
                                        data-test="archive-project-{{ $project->id }}"
                                    />
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="$canManage ? 9 : 8" class="py-8 text-center">{{ __('No projects match your search.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-footer :paginator="$this->projects" />
    </div>
</div>
