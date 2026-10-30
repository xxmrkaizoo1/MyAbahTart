<x-layouts.shop title="My orders">
    <div class="container-page py-6 sm:py-10">
        <p class="eyebrow">Account</p>
        <h1 class="mt-1 text-4xl font-extrabold sm:text-5xl">My orders</h1>

        @if ($activeOrders->isEmpty() && $pastOrders->isEmpty())
            <div class="mt-8 flex flex-col items-center rounded-4xl bg-surface px-6 py-16 text-center shadow-soft ring-1 ring-ink/5">
                <span class="grid size-20 place-items-center rounded-full bg-berry-50 text-berry-600"><x-icon name="receipt" class="size-9" /></span>
                <h2 class="mt-5 text-3xl font-extrabold">No orders yet</h2>
                <p class="mt-2 max-w-sm text-ink-soft">Your first order will show up here, with live tracking.</p>
                <a href="{{ route('products.index') }}" class="btn-primary mt-6 px-7 py-3.5">Start shopping</a>
            </div>
        @endif

        @foreach ($activeOrders as $order)
            <section class="mt-8 overflow-hidden rounded-4xl bg-surface shadow-soft ring-1 ring-ink/5">
                <div class="flex flex-wrap items-center gap-4 bg-plum-900 p-5 text-white sm:p-7">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-white/60">In progress · {{ $order->created_at->format('j M, g:i a') }}</p>
                        <p class="font-display text-2xl font-extrabold sm:text-3xl">{{ $order->statusLabel() }}</p>
                    </div>
                    @if ($estimates[$order->id] && in_array($order->status->value, ['received', 'preparing'], true))
                        <div class="rounded-2xl bg-white/10 px-4 py-2.5 text-right">
                            <p class="text-xs text-white/60">Estimated ready</p>
                            <p class="font-display text-xl font-bold text-butter-300">{{ $estimates[$order->id]->format('g:i a') }}</p>
                        </div>
                    @endif
                </div>
                <div class="p-5 sm:p-7">
                    @include('shop.orders.partials.progress', ['order' => $order, 'estimate' => $estimates[$order->id]])

                    <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-ink/5 pt-5">
                        <div class="flex -space-x-3">
                            @foreach ($order->items->take(4) as $item)
                                @if ($item->product)
                                    <x-product-image :product="$item->product" class="size-11 rounded-full ring-2 ring-surface" />
                                @endif
                            @endforeach
                        </div>
                        <p class="text-sm text-ink-soft"><span class="font-bold text-ink">{{ $order->code }}</span> · {{ $order->itemCount() }} items · @rm($order->total)</p>
                        <a href="{{ route('orders.show', $order) }}" class="btn-dark ml-auto">Order details <x-icon name="arrow-right" class="size-4" /></a>
                    </div>
                </div>
            </section>
        @endforeach

        @if ($pastOrders->isNotEmpty())
            <section class="mt-12">
                <h2 class="section-title">Past orders</h2>
                <ul class="mt-5 grid gap-3 md:grid-cols-2">
                    @foreach ($pastOrders as $order)
                        @php($firstItem = $order->items->first())
                        <li class="flex items-center gap-4 rounded-3xl bg-surface p-3 ring-1 ring-ink/5 sm:p-4">
                            @if ($firstItem?->product)
                                <x-product-image :product="$firstItem->product" class="size-16 shrink-0 rounded-2xl" />
                            @endif
                            <a href="{{ route('orders.show', $order) }}" class="min-w-0 flex-1">
                                <p class="truncate font-bold">{{ $firstItem?->displayName() }}{{ $order->items->count() > 1 ? ' + '.($order->items->count() - 1).' more' : '' }}</p>
                                <p class="text-xs text-ink-soft">{{ $order->code }} · {{ $order->created_at->format('j M Y') }} · @rm($order->total)</p>
                                <x-status-badge :order="$order" class="mt-1.5" />
                            </a>
                            <form method="POST" action="{{ route('orders.reorder', $order) }}">
                                @csrf
                                <button class="grid size-10 place-items-center rounded-full bg-paper-deep hover:bg-berry-600 hover:text-white sm:hidden" aria-label="Order again"><x-icon name="refresh" class="size-4" /></button>
                                <button class="btn-outline hidden py-2 sm:inline-flex"><x-icon name="refresh" class="size-4" /> Order again</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.shop>
