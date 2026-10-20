@if ($material->status === \App\Models\Material::STATUS_ACTIVE)
    <x-status-badge color="green">{{ __('Active') }}</x-status-badge>
@else
    <x-status-badge color="zinc">{{ __('Inactive') }}</x-status-badge>
@endif
