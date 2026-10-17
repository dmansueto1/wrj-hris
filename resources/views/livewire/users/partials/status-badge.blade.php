@if ($user->status === \App\Models\User::STATUS_ACTIVE)
    <x-status-badge color="green">{{ __('Active') }}</x-status-badge>
@else
    <x-status-badge color="zinc">{{ __('Inactive') }}</x-status-badge>
@endif
