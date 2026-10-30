{{-- Refund history for an order. Expects $order with refunds.items.orderItem loaded, or a $refunds list. --}}
<ul class="space-y-3">
    @foreach (($refunds ?? $order->refunds)->sortByDesc('created_at') as $refund)
        <li class="rounded-2xl bg-paper p-4 text-sm ring-1 ring-ink/5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $refund->status->badgeClasses() }}">{{ $refund->status->label() }}</span>
                <span class="text-xs text-ink-soft">{{ $refund->created_at->format('j M Y, g:i a') }}</span>
            </div>
            <p class="mt-2 font-bold">{{ $refund->reason->label() }}</p>
            @if ($refund->details)
                <p class="mt-0.5 text-ink-soft">“{{ $refund->details }}”</p>
            @endif
            @if ($refund->items->isNotEmpty())
                <ul class="mt-2 space-y-0.5 text-xs text-ink-soft">
                    @foreach ($refund->items as $refundItem)
                        <li>{{ $refundItem->quantity }} × {{ $refundItem->orderItem->displayName() }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($refund->status === \App\Enums\RefundStatus::Approved)
                <p class="mt-3 flex flex-wrap items-center gap-x-2 rounded-xl bg-mint-50 px-3 py-2 text-mint-700">
                    <x-icon name="check" class="size-4" />
                    <span class="font-bold">@rm($refund->amount)</span> refunded by {{ $refund->method->label() }}
                    @if ($refund->restocked && ($showStaffDetails ?? false))
                        <span class="text-xs">· items restocked</span>
                    @endif
                </p>
            @endif
            @if ($refund->admin_note)
                <p class="mt-2 rounded-xl bg-surface px-3 py-2 text-ink-soft ring-1 ring-ink/5"><span class="font-semibold text-ink">{{ ($showStaffDetails ?? false) ? 'Note' : 'From the shop' }}:</span> {{ $refund->admin_note }}</p>
            @endif
            @if (($showStaffDetails ?? false) && $refund->processor)
                <p class="mt-2 text-xs text-ink-soft">Handled by {{ $refund->processor->name }} · {{ $refund->processed_at?->diffForHumans() }}</p>
            @endif
        </li>
    @endforeach
</ul>
