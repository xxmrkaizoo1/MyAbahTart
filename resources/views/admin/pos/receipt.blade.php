<x-layouts.admin :title="'Receipt '.$order->code" :subtitle="$order->created_at->format('l, j F Y \a\t g:i a')">
    <x-slot:actions>
        <button type="button" onclick="window.print()" class="btn-outline"><x-icon name="receipt" class="size-4" /> Print</button>
        <a href="{{ route('admin.pos.index') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New sale</a>
    </x-slot:actions>

    <div data-pos-sale-complete class="mx-auto max-w-sm">
        <article class="card p-6 font-mono text-sm print:p-0 print:shadow-none print:ring-0">
            <header class="text-center">
                <p class="font-display text-xl font-extrabold">MYabah Tart Lejen</p>
                <p class="text-xs text-ink-soft">18, Jalan Telawi 3, Bangsar Baru, KL</p>
                <p class="mt-3 text-xs">{{ $order->created_at->format('j/m/Y g:i a') }} · {{ $order->code }}</p>
                <p class="text-xs text-ink-soft">Served by {{ $order->cashier?->name ?? 'staff' }}@if ($order->customer_name) · for {{ $order->customer_name }}@endif</p>
            </header>

            <ul class="mt-4 space-y-2 border-y border-dashed border-ink/30 py-3">
                @foreach ($order->items as $item)
                    <li>
                        <div class="flex justify-between gap-3"><span>{{ $item->displayName() }}</span><span>{{ number_format((float) $item->line_total, 2) }}</span></div>
                        <div class="text-xs text-ink-soft">{{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2) }}</div>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-3 space-y-1">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ number_format((float) $order->subtotal, 2) }}</dd></div>
                @if ((float) $order->discount > 0)
                    <div class="flex justify-between"><dt>Discount</dt><dd>-{{ number_format((float) $order->discount, 2) }}</dd></div>
                @endif
                <div class="flex justify-between text-base font-bold"><dt>TOTAL (RM)</dt><dd>{{ number_format((float) $order->total, 2) }}</dd></div>
                <div class="flex justify-between pt-2"><dt>{{ $order->payment_method?->label() }}</dt><dd>{{ number_format((float) ($order->amount_tendered ?? $order->total), 2) }}</dd></div>
                @if ($order->changeGiven() !== null)
                    <div class="flex justify-between font-bold"><dt>Change</dt><dd>{{ number_format($order->changeGiven(), 2) }}</dd></div>
                @endif
            </dl>

            @if ($order->refundedAmount() > 0)
                <p class="mt-3 border-t border-dashed border-ink/30 pt-3">Refunded: -{{ number_format($order->refundedAmount(), 2) }}</p>
            @endif

            <p class="mt-5 text-center text-xs text-ink-soft">Thank you! Prepared fresh with care.</p>
        </article>

        <a href="{{ route('admin.orders.show', $order) }}" class="btn-ghost mt-3 w-full print:hidden">Order details &amp; refunds</a>
    </div>
</x-layouts.admin>
