<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('users.create')" wire:navigate data-test="add-user-button">
            {{ __('Add user') }}
        </flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search name, username or employee no.')"
            :aria-label="__('Search users')"
        />

        <flux:select wire:model.live="role" :aria-label="__('Filter by role')">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach ($roles as $roleOption)
                <flux:select.option :value="$roleOption">{{ __(ucfirst($roleOption)) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" :aria-label="__('Filter by status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white px-4 py-2 sm:px-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Username') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Position') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Hourly rate') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <flux:link :href="route('users.edit', $user)" wire:navigate>{{ $user->employee_no }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->username }}</flux:table.cell>
                        <flux:table.cell>{{ $user->roleLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $user->position ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ \App\Support\Money::format($user->hourly_rate) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->status === \App\Models\User::STATUS_ACTIVE)
                                <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center">{{ __('No users match your search.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-footer :paginator="$this->users" />
    </div>
</div>
