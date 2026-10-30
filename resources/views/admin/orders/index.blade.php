<x-layouts.admin title="Orders" subtitle="Move orders across the board as the kitchen works through them.">
    <div data-live="kitchen-focus">
    @if ($kitchenFocus)
        <x-insight title="Batch suggestion" class="mb-6">
            <span class="font-bold text-ink">{{ $kitchenFocus['units'] }}× {{ $kitchenFocus['product_name'] }}</span> are needed across {{ $kitchenFocus['orders'] }} waiting {{ Str::plural('order', $kitchenFocus['orders']) }}. Prepare them together to save time.
        </x-insight>
    @endif
    </div>

    {{-- Kanban board --}}
    <div data-live="board" class="scrollbar-none -mx-4 flex snap-x snap-mandatory scroll-px-4 items-start gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 lg:grid lg:grid-cols-3 lg:overflow-visible">
        @foreach ([
            ['received', 'New', 'bell', 'bg-sky-500'],
            ['preparing', 'Being prepared', 'whisk', 'bg-butter-400'],
            ['ready', 'Ready / out for delivery', 'truck', 'bg-mint-500'],
        ] as [$value, $label, $icon, $dot])
            @php($column = $board[$value] ?? collect())
            <section class="flex w-[85%] shrink-0 snap-start flex-col rounded-3xl bg-surface/70 p-3 ring-1 ring-ink/5 sm:w-[60%] lg:w-auto">
                <header class="flex items-center gap-2 px-2 py-2">
                    <span class="size-2.5 rounded-full {{ $dot }}"></span>
                    <h2 class="font-display text-lg font-bold">{{ $label }}</h2>
                    <span class="ml-auto rounded-full bg-ink/5 px-2.5 py-0.5 text-xs font-extrabold">{{ $column->count() }}</span>
                </header>
                <div class="mt-1 max-h-[32rem] space-y-2.5 overflow-y-auto">
                    @forelse ($column as $order)
                        <article class="rounded-2xl bg-surface p-4 shadow-soft ring-1 ring-ink/5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-bold hover:text-berry-700">{{ $order->customerName() }}</a>
                                    <p class="text-xs text-ink-soft">{{ $order->code }} · {{ $order->created_at->diffForHumans(short: true) }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-extrabold">@rm($order->total)</span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm text-ink-soft">{{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->displayName())->implode(', ') }}</p>
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-ink-soft"><x-icon :name="$order->delivery_method->icon()" class="size-4" /> {{ $order->delivery_method->label() }}</span>
                                @include('admin.orders.partials.advance-button', ['order' => $order])
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl border-2 border-dashed border-ink/10 p-6 text-center text-sm text-ink-soft">Nothing here</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{-- All orders --}}
    <section class="card mt-8 overflow-hidden">
        <div class="flex flex-col gap-4 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-display text-xl font-bold">All orders</h2>
                <p class="text-sm text-ink-soft">{{ $completedToday }} completed today</p>
            </div>
            <form class="relative md:w-80">
                @if ($activeStatus)
                    <input type="hidden" name="status" value="{{ $activeStatus->value }}">
                @endif
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                <input type="search" name="search" value="{{ $search }}" placeholder="Order code or customer" class="input rounded-full bg-paper pl-10" aria-label="Search orders">
            </form>
        </div>

        <nav data-live="status-tabs" class="scrollbar-none flex gap-1.5 overflow-x-auto border-y border-ink/5 bg-paper/60 px-5 py-3 sm:px-6">
            <a href="{{ route('admin.orders.index', array_filter(['search' => $search])) }}" @class([
                'shrink-0 rounded-full px-3.5 py-1.5 text-sm font-bold',
                'bg-plum-900 text-white' => ! $activeStatus,
                'text-ink-soft hover:bg-surface' => $activeStatus,
            ])>All</a>
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <a href="{{ route('admin.orders.index', array_filter(['status' => $status->value, 'search' => $search])) }}" @class([
                    'flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-bold',
                    'bg-plum-900 text-white' => $activeStatus === $status,
                    'text-ink-soft hover:bg-surface' => $activeStatus !== $status,
                ])>
                    {{ $status->shortLabel() }}
                    @if (($counts[$status->value] ?? 0) > 0 && $status->isActive())
                        <span class="rounded-full bg-berry-500 px-1.5 text-[10px] text-white">{{ $counts[$status->value] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @if ($orders->isEmpty())
            <p class="p-12 text-center text-sm text-ink-soft">No orders match.</p>
        @else
            <div data-live="table" class="relative hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs font-bold text-ink-soft">
                        <tr>
                            <th class="px-6 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Fulfilment</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-6 py-3"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-paper/60">
                                <td class="px-6 py-3.5">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-bold text-berry-700 hover:underline">{{ $order->code }}</a>
                                    <p class="text-xs text-ink-soft">{{ $order->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-3.5 font-semibold">{{ $order->customerName() }}</td>
                                <td class="max-w-56 px-4 py-3.5"><p class="truncate text-ink-soft">{{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->displayName())->implode(', ') }}</p></td>
                                <td class="px-4 py-3.5 text-ink-soft">{{ $order->delivery_method->label() }}</td>
                                <td class="px-4 py-3.5"><div class="flex flex-wrap gap-1"><x-status-badge :order="$order" /><x-payment-badge :order="$order" /></div></td>
                                <td class="px-4 py-3.5 text-right font-bold">@rm($order->total)</td>
                                <td class="px-6 py-3.5 text-right">@include('admin.orders.partials.advance-button', ['order' => $order])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul data-live="table-mobile" class="divide-y divide-ink/5 md:hidden">
                @foreach ($orders as $order)
                    <li class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="min-w-0">
                                <p class="font-bold text-berry-700">{{ $order->code }}</p>
                                <p class="truncate text-sm font-semibold">{{ $order->customerName() }}</p>
                                <p class="text-xs text-ink-soft">{{ $order->created_at->diffForHumans() }} · {{ $order->delivery_method->label() }}</p>
                            </a>
                            <p class="font-bold">@rm($order->total)</p>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="flex flex-wrap gap-1"><x-status-badge :order="$order" /><x-payment-badge :order="$order" /></div>
                            @include('admin.orders.partials.advance-button', ['order' => $order])
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="border-t border-ink/5 p-5 sm:px-6"><x-pagination :paginator="$orders" /></div>
        @endif
    </section>
</x-layouts.admin>
