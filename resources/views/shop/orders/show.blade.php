<x-layouts.shop :title="'Order '.$order->code">
    <div class="container-page py-6 sm:py-10">
        <a href="{{ route('orders.index') }}" class="btn-ghost -ml-4"><x-icon name="arrow-left" class="size-4" /> All orders</a>

        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Order {{ $order->code }}</p>
                <h1 class="mt-1 text-4xl font-extrabold sm:text-5xl">{{ $order->statusLabel() }}</h1>
                <p class="mt-2 text-ink-soft">Placed {{ $order->created_at->format('l, j F Y \a\t g:i a') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($order->pendingRefund())
                    <a href="#refunds" class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-bold ring-1 {{ \App\Enums\RefundStatus::Requested->badgeClasses() }}">Refund requested</a>
                @elseif ($order->refundedAmount() > 0)
                    <a href="#refunds" class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-bold ring-1 {{ \App\Enums\RefundStatus::Approved->badgeClasses() }}">{{ $order->isFullyRefunded() ? 'Refunded' : 'Partly refunded' }}</a>
                @endif
                <x-status-badge :order="$order" class="text-sm" />
            </div>
        </div>

        <section class="mt-8 rounded-4xl bg-surface p-5 shadow-soft ring-1 ring-ink/5 sm:p-8">
            @include('shop.orders.partials.progress', ['order' => $order, 'estimate' => $estimate])
            @include('shop.orders.partials.cancellation', ['order' => $order])
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px] lg:items-start">
            <section class="rounded-4xl bg-surface p-5 shadow-soft ring-1 ring-ink/5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Items ({{ $order->itemCount() }})</h2>
                <ul class="mt-4 divide-y divide-ink/5">
                    @foreach ($order->items as $item)
                        <li class="flex items-center gap-4 py-3">
                            @if ($item->product)
                                <x-product-image :product="$item->product" class="size-16 shrink-0 rounded-2xl" />
                            @endif
                            <div class="min-w-0 flex-1">
                                @if ($item->product?->is_active)
                                    <a href="{{ route('products.show', $item->product) }}" class="font-bold hover:text-berry-700">{{ $item->displayName() }}</a>
                                @else
                                    <p class="font-bold">{{ $item->displayName() }}</p>
                                @endif
                                <p class="text-sm text-ink-soft">{{ $item->quantity }} × @rm($item->unit_price)</p>
                            </div>
                            <p class="font-bold">@rm($item->line_total)</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2 border-t border-dashed border-ink/15 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd>@rm($order->subtotal)</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-soft">Delivery</dt><dd>{{ (float) $order->delivery_fee === 0.0 ? 'FREE' : 'RM '.number_format((float) $order->delivery_fee, 2) }}</dd></div>
                    @if ((float) $order->discount > 0)
                        <div class="flex justify-between text-mint-600"><dt>Discount ({{ $order->voucher_code }})</dt><dd>−@rm($order->discount)</dd></div>
                    @endif
                    <div class="flex items-end justify-between pt-2"><dt class="font-bold">Total</dt><dd class="font-display text-3xl font-extrabold text-berry-700">@rm($order->total)</dd></div>
                    @if ($order->refundedAmount() > 0)
                        <div class="flex justify-between text-rose-700"><dt>Refunded</dt><dd>−@rm($order->refundedAmount())</dd></div>
                    @endif
                </dl>
            </section>

            <aside class="space-y-4">
                @if ($estimate && in_array($order->status->value, ['received', 'preparing'], true))
                    <x-insight title="Estimated ready time" tone="butter">
                        Based on the current kitchen queue, we expect your order to be ready around <span class="font-bold text-ink">{{ $estimate->format('g:i a') }}</span>.
                    </x-insight>
                @endif

                <div class="rounded-3xl bg-surface p-5 ring-1 ring-ink/5">
                    <p class="eyebrow">{{ $order->delivery_method->label() }}</p>
                    <div class="mt-3 space-y-2.5 text-sm">
                        <p class="flex gap-2.5"><x-icon name="phone" class="size-4.5 text-ink-soft" /> {{ $order->phone }}</p>
                        <p class="flex gap-2.5"><x-icon :name="$order->address ? 'map-pin' : 'store'" class="size-4.5 text-ink-soft" /> {{ $order->address ?? 'MYabah Tart Lejen, 18 Jalan Telawi 3, Bangsar Baru' }}</p>
                        @if ($order->notes)
                            <p class="rounded-2xl bg-paper p-3 text-ink-soft">“{{ $order->notes }}”</p>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('orders.reorder', $order) }}">
                    @csrf
                    <button class="btn-dark w-full py-3"><x-icon name="refresh" class="size-4" /> Order again</button>
                </form>
                @if ($order->status->value === 'received')
                    <button type="button" data-dialog-open="cancel-order" class="btn-ghost w-full text-rose-700">Cancel this order</button>
                @endif

                @if ($order->refunds->isNotEmpty())
                    <section id="refunds" class="rounded-3xl bg-surface p-5 ring-1 ring-ink/5">
                        <p class="eyebrow">Refunds</p>
                        <div class="mt-3">@include('shop.orders.partials.refunds', ['order' => $order])</div>
                    </section>
                @endif

                @if ($order->canRequestRefund())
                    @php($refundable = $order->refundableQuantities())
                    <details id="request-refund" class="group rounded-3xl bg-surface ring-1 ring-ink/5" @if ($errors->hasAny(['reason', 'details', 'items']) || $errors->has('items.*')) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-3 p-5">
                            <span class="grid size-10 place-items-center rounded-2xl bg-rose-50 text-rose-700"><x-icon name="refresh" class="size-5" /></span>
                            <span class="flex-1">
                                <span class="block font-bold">Something wrong? Request a refund</span>
                                <span class="block text-xs text-ink-soft">Available until {{ $order->completed_at->addDays(\App\Models\Refund::REQUEST_WINDOW_DAYS)->format('j M, g:i a') }}</span>
                            </span>
                            <x-icon name="chevron-down" class="size-5 text-ink-soft transition group-open:rotate-180" />
                        </summary>
                        <form method="POST" action="{{ route('refunds.store', $order) }}" class="space-y-4 border-t border-ink/5 p-5">
                            @csrf
                            <fieldset>
                                <legend class="label">Which items?</legend>
                                <ul class="space-y-2">
                                    @foreach ($order->items as $item)
                                        @continue(($refundable[$item->id] ?? 0) === 0)
                                        <li class="flex items-center gap-3 rounded-2xl bg-paper p-2.5 text-sm">
                                            <span class="min-w-0 flex-1 truncate font-semibold">{{ $item->displayName() }}</span>
                                            <select name="items[{{ $item->id }}]" class="input w-20 bg-surface py-1.5" aria-label="Quantity of {{ $item->displayName() }} to refund">
                                                @for ($quantity = 0; $quantity <= $refundable[$item->id]; $quantity++)
                                                    <option value="{{ $quantity }}" @selected((int) old('items.'.$item->id, 0) === $quantity)>{{ $quantity }}</option>
                                                @endfor
                                            </select>
                                        </li>
                                    @endforeach
                                </ul>
                                @error('items') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                                @error('items.*') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            </fieldset>
                            <div>
                                <label for="reason" class="label">What happened?</label>
                                <select id="reason" name="reason" required class="input bg-paper">
                                    @foreach (\App\Enums\RefundReason::cases() as $reason)
                                        <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="details" class="label">Details <span class="font-normal text-ink-soft">(optional)</span></label>
                                <textarea id="details" name="details" rows="3" maxlength="1000" placeholder="Tell us a little more so we can sort it out" class="input bg-paper">{{ old('details') }}</textarea>
                                @error('details') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            </div>
                            <button class="btn-dark w-full">Send refund request</button>
                        </form>
                    </details>
                @endif
            </aside>
        </div>
    </div>

    @if ($order->status === \App\Enums\OrderStatus::Received)
        <dialog id="cancel-order" data-dialog @if ($errors->hasAny(['cancellation_reason', 'cancellation_note'])) data-dialog-autoshow @endif
            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-4xl bg-surface p-0 text-ink shadow-pop backdrop:bg-plum-950/50 backdrop:backdrop-blur-sm" aria-labelledby="cancel-order-title">
            <form method="POST" action="{{ route('orders.cancel', $order) }}" class="p-5 sm:p-7">
                @csrf

                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-50 text-rose-700"><x-icon name="alert" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 id="cancel-order-title" class="font-display text-xl font-bold">Cancel order {{ $order->code }}?</h2>
                        <p class="text-sm text-ink-soft">Let us know why so we can do better next time.</p>
                    </div>
                    <button type="button" data-dialog-close class="-mt-1 -mr-1 grid size-10 place-items-center rounded-full hover:bg-ink/5" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </div>

                <fieldset class="mt-5">
                    <legend class="label">Why are you cancelling?</legend>
                    <div class="grid gap-2">
                        @foreach (\App\Enums\CancellationReason::forCustomers() as $reason)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-paper px-4 py-3 text-sm font-semibold ring-1 ring-ink/5 transition has-checked:bg-rose-50 has-checked:ring-2 has-checked:ring-rose-500">
                                <input type="radio" name="cancellation_reason" value="{{ $reason->value }}" class="text-rose-600" @checked(old('cancellation_reason') === $reason->value) required>
                                {{ $reason->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('cancellation_reason') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>

                <label for="cancellation_note" class="label mt-4">Anything else? <span class="font-normal text-ink-soft">· optional, needed for “Something else”</span></label>
                <textarea id="cancellation_note" name="cancellation_note" rows="3" maxlength="500" class="input bg-paper" placeholder="e.g. I’ll order again for pickup instead.">{{ old('cancellation_note') }}</textarea>
                @error('cancellation_note') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-dialog-close class="btn-outline">Keep order</button>
                    <button class="btn bg-rose-600 text-white hover:bg-rose-700">Cancel order</button>
                </div>
            </form>
        </dialog>
    @endif
</x-layouts.shop>
