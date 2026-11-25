{{--
    History panel (T-17, Admin only: wrap it in @can('audit.view')). Who changed what, when and why,
    newest first. $entries: AuditLogService rows.
--}}
@props(['entries', 'limit' => \App\Services\AuditLogService::LIMIT])

<section {{ $attributes->class('flex flex-col gap-4') }} data-test="audit-history">
    <div>
        <flux:heading size="lg" level="2">{{ __('History') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Who changed what, when and why. The latest :limit changes.', ['limit' => $limit]) }}</flux:text>
    </div>

    @if ($entries->isEmpty())
        <p class="rounded-xl bg-brand-page px-4 py-3 text-sm text-zinc-500">{{ __('No changes recorded yet.') }}</p>
    @else
        <ol class="flex flex-col gap-3">
            @foreach ($entries as $entry)
                <li class="flex gap-3 rounded-xl bg-brand-page px-4 py-3 text-sm" wire:key="audit-{{ $loop->index }}">
                    <flux:icon icon="pencil-square" class="mt-0.5 size-4 shrink-0 text-brand-blue" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-brand-navy">{{ __($entry['action']) }}</p>
                        <p class="text-xs text-zinc-500">{{ $entry['by'] }} &middot; {{ $entry['at']->format('M j, Y g:i A') }}</p>

                        @if ($entry['changes'] !== [])
                            <dl class="mt-2 grid gap-1">
                                @foreach ($entry['changes'] as $change)
                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                        <dt class="text-zinc-500">{{ $change['label'] }}</dt>
                                        <dd class="tabular-nums text-zinc-700">
                                            <span class="text-zinc-500 line-through decoration-zinc-300">{{ $change['old'] }}</span>
                                            <span aria-hidden="true">&rarr;</span>
                                            <span class="sr-only">{{ __('to') }}</span>
                                            <span class="font-semibold text-brand-navy">{{ $change['new'] }}</span>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if ($entry['reason'])
                            <p class="mt-2 text-zinc-700"><span class="text-zinc-500">{{ __('Reason:') }}</span> {{ $entry['reason'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
