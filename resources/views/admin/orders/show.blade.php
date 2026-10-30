<x-layouts.admin :title="'Order '.$order->code" :subtitle="'Placed '.$order->created_at->format('l, j F Y \a\t g:i a').' · '.$order->created_at->diffForHumans()">
    <x-slot:actions>
        <a href="{{ route('admin.orders.index') }}" class="btn-outline"><x-icon name="arrow-left" class="size-4" /> Back to orders</a>
    </x-slot:actions>

    <section class="card p-5 sm:p-8">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
            <x-status-badge :order="$order" class="text-sm" />
            @if ($order->status->isActive())
                <div class="flex items-center gap-2">
                    <button type="button" data-dialog-open="cancel-order" class="btn-ghost text-rose-700">Cancel order</button>
                    @include('admin.orders.partials.advance-button', ['order' => $order])
                </div>
            @endif
        </div>
        @include('shop.orders.partials.progress', ['order' => $order, 'estimate' => $estimate])
        @include('shop.orders.partials.cancellation', ['order' => $order])
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px] lg:items-start">
        <section class="card overflow-hidden">
            <h2 class="px-5 pt-5 font-display text-xl font-bold sm:px-6">Items</h2>
            <ul class="mt-3 divide-y divide-ink/5">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-4 px-5 py-3 sm:px-6">
                        @if ($item->product)
                            <x-product-image :product="$item->product" class="size-14 shrink-0 rounded-2xl" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">{{ $item->displayName() }}</p>
                            <p class="text-xs text-ink-soft">{{ $item->quantity }} × @rm($item->unit_price)</p>
                        </div>
                        <p class="font-bold">@rm($item->line_total)</p>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-2 bg-paper px-5 py-4 text-sm sm:px-6">
                <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd>@rm($order->subtotal)</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">Delivery</dt><dd>@rm($order->delivery_fee)</dd></div>
                @if ((float) $order->discount > 0)
                    <div class="flex justify-between text-mint-600"><dt>Discount ({{ $order->voucher_code }})</dt><dd>−@rm($order->discount)</dd></div>
                @endif
                <div class="flex items-end justify-between pt-1"><dt class="font-bold">Total</dt><dd class="font-display text-2xl font-extrabold text-berry-700">@rm($order->total)</dd></div>
                @if ($order->refundedAmount() > 0)
                    <div class="flex justify-between font-semibold text-rose-700"><dt>Refunded</dt><dd>−@rm($order->refundedAmount())</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-soft">Kept after refunds</dt><dd class="font-bold">@rm($order->refundableAmount())</dd></div>
                @endif
            </dl>
        </section>

        <aside class="space-y-4">
            <div class="card p-5">
                <p class="eyebrow">Customer</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-full bg-butter-200 text-sm font-extrabold">{{ $order->customerInitials() }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ $order->customerName() }}</p>
                        <p class="truncate text-xs text-ink-soft">{{ $order->user?->email ?? 'Counter sale · no account' }}</p>
                    </div>
                </div>
                @if ($order->phone)
                    <a href="tel:{{ $order->phone }}" class="btn-outline mt-4 w-full"><x-icon name="phone" class="size-4" /> {{ $order->phone }}</a>
                @endif
            </div>

            @if ($order->isCounterSale())
                <div class="card p-5">
                    <p class="eyebrow">Counter sale</p>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-soft">Paid by</dt><dd class="font-semibold">{{ $order->payment_method?->label() }}</dd></div>
                        @if ($order->changeGiven() !== null)
                            <div class="flex justify-between"><dt class="text-ink-soft">Cash received</dt><dd>@rm($order->amount_tendered)</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-soft">Change</dt><dd>@rm($order->changeGiven())</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-ink-soft">Served by</dt><dd>{{ $order->cashier?->name ?? '—' }}</dd></div>
                    </dl>
                    <a href="{{ route('admin.pos.receipt', $order) }}" class="btn-outline mt-4 w-full"><x-icon name="receipt" class="size-4" /> View receipt</a>
                </div>
            @else
                <div class="card p-5">
                    <p class="eyebrow">{{ $order->delivery_method->label() }}</p>
                    <p class="mt-3 flex gap-2.5 text-sm"><x-icon :name="$order->delivery_method->icon()" class="size-4.5 shrink-0 text-ink-soft" /> {{ $order->address ?? 'Customer will collect from the shop.' }}</p>
                    @if ($order->notes)
                        <p class="mt-3 rounded-2xl bg-butter-100 p-3 text-sm"><span class="font-bold">Note:</span> {{ $order->notes }}</p>
                    @endif
                </div>
            @endif

            @php($pendingRefund = $order->pendingRefund())
            @if ($pendingRefund)
                <section id="refunds" class="card p-5 ring-2 ring-butter-400">
                    <p class="eyebrow">Refund requested</p>
                    <p class="mt-2 font-bold">{{ $pendingRefund->reason->label() }}</p>
                    @if ($pendingRefund->details)
                        <p class="mt-1 text-sm text-ink-soft">“{{ $pendingRefund->details }}”</p>
                    @endif
                    <ul class="mt-3 space-y-1 rounded-2xl bg-paper p-3 text-sm">
                        @foreach ($pendingRefund->items as $refundItem)
                            <li class="flex justify-between gap-2"><span>{{ $refundItem->quantity }} × {{ $refundItem->orderItem->displayName() }}</span><span class="font-semibold">@rm($refundItem->value())</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-ink-soft">Requested {{ $pendingRefund->created_at->diffForHumans() }}</p>

                    <form method="POST" action="{{ route('admin.refunds.update', $pendingRefund) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        @include('admin.refunds.partials.payout-fields', ['order' => $order, 'suggestedAmount' => min($pendingRefund->itemsValue(), $order->refundableAmount()), 'prefix' => 'approve'])
                        <div class="grid grid-cols-2 gap-2">
                            <button name="decision" value="reject" formnovalidate class="btn-outline text-rose-700" data-confirm="Decline this refund? Add a note explaining why.">Decline</button>
                            <button name="decision" value="approve" class="btn-primary">Approve</button>
                        </div>
                    </form>
                </section>
            @elseif ($order->canBeRefunded())
                @php($refundable = $order->refundableQuantities())
                <details id="refunds" class="card group" @if ($errors->getBag('refund')->any()) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 p-5">
                        <span class="grid size-10 place-items-center rounded-2xl bg-rose-50 text-rose-700"><x-icon name="refresh" class="size-5" /></span>
                        <span class="flex-1 font-bold">Issue a refund</span>
                        <x-icon name="chevron-down" class="size-5 text-ink-soft transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('admin.refunds.store', $order) }}" class="space-y-3 border-t border-ink/5 p-5">
                        @csrf
                        <div>
                            <label for="issue-reason" class="label">Reason</label>
                            <select id="issue-reason" name="reason" class="input bg-paper">
                                @foreach (\App\Enums\RefundReason::cases() as $reason)
                                    <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (array_sum($refundable) > 0)
                            <fieldset>
                                <legend class="label">Items returned <span class="font-normal text-ink-soft">(optional)</span></legend>
                                <ul class="space-y-1.5">
                                    @foreach ($order->items as $item)
                                        @continue(($refundable[$item->id] ?? 0) === 0)
                                        <li class="flex items-center gap-2 text-sm">
                                            <span class="min-w-0 flex-1 truncate">{{ $item->displayName() }}</span>
                                            <select name="items[{{ $item->id }}]" class="input w-20 bg-paper py-1.5" aria-label="Quantity of {{ $item->displayName() }}">
                                                @for ($quantity = 0; $quantity <= $refundable[$item->id]; $quantity++)
                                                    <option value="{{ $quantity }}">{{ $quantity }}</option>
                                                @endfor
                                            </select>
                                        </li>
                                    @endforeach
                                </ul>
                            </fieldset>
                        @endif
                        @include('admin.refunds.partials.payout-fields', ['order' => $order, 'suggestedAmount' => $order->refundableAmount(), 'prefix' => 'issue'])
                        <button class="btn-dark w-full" data-confirm="Record this refund? Make sure you've paid the customer back.">Record refund</button>
                    </form>
                </details>
            @endif

            @php($handledRefunds = $order->refunds->where('status', '!=', \App\Enums\RefundStatus::Requested))
            @if ($handledRefunds->isNotEmpty())
                <div class="card p-5">
                    <p class="eyebrow">Refund history</p>
                    <div class="mt-3">@include('shop.orders.partials.refunds', ['order' => $order, 'refunds' => $handledRefunds, 'showStaffDetails' => true])</div>
                </div>
            @endif

            @if ($estimate && $order->status->isActive())
                <x-insight title="Estimated ready" tone="butter">
                    Based on the current queue, this order should be ready around <span class="font-bold text-ink">{{ $estimate->format('g:i a') }}</span>.
                </x-insight>
            @endif
        </aside>
    </div>
    @if ($order->status->isActive())
        <dialog id="cancel-order" data-dialog @if ($errors->hasAny(['cancellation_reason', 'cancellation_note'])) data-dialog-autoshow @endif
            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-4xl bg-surface p-0 text-ink shadow-pop backdrop:bg-plum-950/50 backdrop:backdrop-blur-sm" aria-labelledby="cancel-order-title">
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="p-5 sm:p-7">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="cancelled">

                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-50 text-rose-700"><x-icon name="alert" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 id="cancel-order-title" class="font-display text-xl font-bold">Cancel order {{ $order->code }}?</h2>
                        <p class="text-sm text-ink-soft">{{ $order->customerName() }} will see the reason on their order. Stock goes back on the shelf.</p>
                    </div>
                    <button type="button" data-dialog-close class="-mt-1 -mr-1 grid size-10 place-items-center rounded-full hover:bg-ink/5" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </div>

                <fieldset class="mt-5">
                    <legend class="label">Reason</legend>
                    <div class="grid gap-2">
                        @foreach (\App\Enums\CancellationReason::forShop() as $reason)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-paper px-4 py-3 text-sm font-semibold ring-1 ring-ink/5 transition has-checked:bg-rose-50 has-checked:ring-2 has-checked:ring-rose-500">
                                <input type="radio" name="cancellation_reason" value="{{ $reason->value }}" class="text-rose-600" @checked(old('cancellation_reason') === $reason->value) required>
                                {{ $reason->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('cancellation_reason') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>

                <label for="cancellation_note" class="label mt-4">Message to the customer <span class="font-normal text-ink-soft">· optional, needed for “Something else”</span></label>
                <textarea id="cancellation_note" name="cancellation_note" rows="3" maxlength="500" class="input bg-paper" placeholder="e.g. We can’t deliver to Shah Alam yet. Please order again with self pickup.">{{ old('cancellation_note') }}</textarea>
                @error('cancellation_note') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-dialog-close class="btn-outline">Keep order</button>
                    <button class="btn bg-rose-600 text-white hover:bg-rose-700">Cancel order</button>
                </div>
            </form>
        </dialog>
    @endif
</x-layouts.admin>
