{{-- Fields and live cost preview shared by the create and edit material entry forms. --}}
<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
    <flux:input type="date" wire:model="entry_date" :label="__('Date')" :max="today()->toDateString()" required data-test="entry-date" />

    <flux:select wire:model="project_id" :label="__('Project')" :placeholder="__('Choose a project')" required data-test="entry-project">
        @foreach ($this->projects as $option)
            <flux:select.option :value="$option->id">{{ $option->code }} · {{ $option->name }}@if ($option->status === \App\Models\Project::STATUS_ON_HOLD) ({{ __('On hold') }})@endif</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model.live="material_id" :label="__('Material')" :placeholder="__('Choose a material')" required data-test="entry-material">
        @foreach ($this->materials as $option)
            <flux:select.option :value="$option->id">{{ $option->name }} ({{ $option->unit }})</flux:select.option>
        @endforeach
    </flux:select>

    @php($unit = $this->materials->firstWhere('id', (int) $material_id)?->unit)

    <flux:field>
        <flux:label>{{ __('Quantity') }}@if ($unit) <span class="ms-1 text-xs font-normal text-zinc-500">({{ $unit }})</span>@endif</flux:label>
        <flux:input wire:model.live.debounce.300ms="quantity" inputmode="decimal" placeholder="0" required data-test="entry-quantity" />
        <flux:error name="quantity" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Unit price (₱)') }} <span class="ms-1 text-xs font-normal text-zinc-500">({{ __('from the default price, can be changed') }})</span></flux:label>
        <flux:input wire:model.live.debounce.300ms="unit_price" inputmode="decimal" placeholder="0.00" required data-test="entry-unit-price" />
        <flux:error name="unit_price" />
    </flux:field>

    <flux:input wire:model="remarks" :label="__('Remarks')" :placeholder="__('Optional, e.g. receipt no.')" maxlength="255" />
</div>

{{-- Live preview: computed on the server with MaterialCost. --}}
<div class="rounded-card bg-brand-tint/60 p-4 ring-1 ring-brand-blue/10" data-test="cost-preview" aria-live="polite">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral">{{ __('Subtotal') }}</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums text-brand-navy">{{ $this->preview ? \App\Support\Money::format($this->preview['subtotal']) : '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral">{{ __('Markup (:percent%)', ['percent' => $this->markupPercent()]) }}</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums text-brand-navy">{{ $this->preview ? \App\Support\Money::format($this->preview['markup']) : '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral">{{ __('Total cost') }}</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-brand-navy">{{ $this->preview ? \App\Support\Money::format($this->preview['total']) : '—' }}</dd>
        </div>
    </dl>
</div>
