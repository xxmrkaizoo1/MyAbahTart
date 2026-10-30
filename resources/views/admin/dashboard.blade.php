@php
    $width = 720;
    $height = 280;
    $pad = ['top' => 20, 'right' => 16, 'bottom' => 40, 'left' => 48];
    $plotWidth = $width - $pad['left'] - $pad['right'];
    $plotHeight = $height - $pad['top'] - $pad['bottom'];

    $peak = max(1, collect($forecast)->flatMap(fn ($point) => [$point['actual'] ?? 0, $point['predicted']])->max());
    $tickStep = $peak > 2000 ? 1000 : ($peak > 800 ? 500 : 200);
    $axisMax = ceil($peak * 1.12 / $tickStep) * $tickStep;
    $stepX = $plotWidth / (count($forecast) - 1);

    $x = fn (int $index) => round($pad['left'] + $stepX * $index, 1);
    $y = fn (float $value) => round($pad['top'] + $plotHeight - ($value / $axisMax) * $plotHeight, 1);

    $pastPoints = collect($forecast)->filter(fn ($point) => ! $point['is_future']);
    $lastPastIndex = $pastPoints->keys()->last();
    $actualLine = $pastPoints->map(fn ($point, $index) => $x($index).','.$y($point['actual']))->implode(' ');
    $actualArea = $x(0).','.$y(0).' '.$actualLine.' '.$x($lastPastIndex).','.$y(0);
    $futureLine = collect($forecast)->filter(fn ($point, $index) => $index >= $lastPastIndex)
        ->map(fn ($point, $index) => $x($index).','.$y($point['is_future'] ? $point['predicted'] : $point['actual']))->implode(' ');
    $modelLine = collect($forecast)->map(fn ($point, $index) => $x($index).','.$y($point['predicted']))->implode(' ');

    $forecastTotal = collect($forecast)->where('is_future', true)->sum('predicted');
    $lastWeekTotal = $pastPoints->sum('actual');
    $forecastChange = $lastWeekTotal > 0 ? round(($forecastTotal - $lastWeekTotal) / $lastWeekTotal * 100, 1) : 0;
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $maxTrendUnits = max(1, $categoryTrends->max('units'));
@endphp

<x-layouts.admin :title="$greeting.', '.auth()->user()->firstName()" :subtitle="'Here’s how the shop is doing · '.now()->format('l, j F Y')">
    <x-slot:actions>
        <div class="inline-flex rounded-full bg-surface p-1 ring-1 ring-ink/10">
            @foreach ([7 => '7 days', 30 => '30 days'] as $value => $label)
                <a href="{{ route('admin.dashboard', ['days' => $value]) }}" @class([
                    'rounded-full px-4 py-1.5 text-sm font-bold transition',
                    'bg-plum-900 text-white' => $days === $value,
                    'text-ink-soft hover:text-ink' => $days !== $value,
                ])>{{ $label }}</a>
            @endforeach
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn-primary"><x-icon name="receipt" class="size-4" /> Manage orders</a>
    </x-slot:actions>

    {{-- KPIs --}}
    <div data-live="kpis" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card overflow-hidden p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-ink-soft">Revenue · {{ $days }}d</p>
                @if ($revenueChange !== null)
                    <span @class(['chip', 'bg-mint-50 text-mint-700' => $revenueChange >= 0, 'bg-rose-50 text-rose-700' => $revenueChange < 0])>
                        <x-icon :name="$revenueChange >= 0 ? 'trending-up' : 'trending-down'" class="size-3.5" /> {{ $revenueChange >= 0 ? '+' : '' }}{{ $revenueChange }}%
                    </span>
                @endif
            </div>
            <p class="mt-2 font-display text-3xl font-extrabold">@rm($revenue)</p>
            <x-sparkline :values="$revenueSeries" class="mt-3 h-10" />
        </div>

        <div class="card overflow-hidden p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-ink-soft">Orders today</p>
                @if ($ordersTodayChange !== null)
                    <span @class(['chip', 'bg-mint-50 text-mint-700' => $ordersTodayChange >= 0, 'bg-rose-50 text-rose-700' => $ordersTodayChange < 0])>
                        {{ $ordersTodayChange >= 0 ? '+' : '' }}{{ $ordersTodayChange }}% vs yesterday
                    </span>
                @endif
            </div>
            <p class="mt-2 font-display text-3xl font-extrabold">{{ $ordersToday }} <span class="text-base font-semibold text-ink-soft">· {{ $activeOrders }} in progress</span></p>
            <x-sparkline :values="$orderSeries" stroke="stroke-mint-500" fill="fill-mint-500/10" class="mt-3 h-10" />
        </div>

        <div class="card p-5">
            <p class="text-sm font-semibold text-ink-soft">Average order value</p>
            <p class="mt-2 font-display text-3xl font-extrabold">@rm($averageOrderValue)</p>
            <p class="mt-3 text-sm text-ink-soft">{{ number_format($orderCount) }} orders in the last {{ $days }} days</p>
        </div>

        <a href="{{ route('admin.products.index', ['stock' => 'low']) }}" @class([
            'block rounded-3xl p-5 transition hover:-translate-y-0.5',
            'bg-rose-600 text-white shadow-pop' => $lowStockProducts->isNotEmpty(),
            'card' => $lowStockProducts->isEmpty(),
        ])>
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold opacity-80">Low stock</p>
                <span class="chip bg-white/20">{{ $lowStockProducts->count() }} {{ Str::plural('item', $lowStockProducts->count()) }}</span>
            </div>
            <p class="mt-2 truncate font-display text-2xl font-extrabold">{{ $lowStockProducts->first()?->name ?? 'All stocked up' }}</p>
            <p class="mt-3 text-sm opacity-80">{{ $lowStockProducts->first() ? 'Only '.$lowStockProducts->first()->stock.' left · tap to restock' : 'Nothing needs attention' }}</p>
        </a>
    </div>

    {{-- Online vs counter --}}
    @php($channelTotal = max($channelSplit['online']['revenue'] + $channelSplit['counter']['revenue'], 0.0001))
    @php($onlineShare = round($channelSplit['online']['revenue'] / $channelTotal * 100))
    <a href="{{ route('admin.sales.index', ['days' => $days]) }}" class="card mt-4 grid gap-4 p-5 transition hover:-translate-y-0.5 sm:grid-cols-[1fr_1fr_auto] sm:items-center sm:p-6">
        @foreach (['online' => ['Online', 'var(--color-online)'], 'counter' => ['Counter', 'var(--color-counter)']] as $key => [$label, $color])
            <div>
                <p class="flex items-center gap-2 text-sm font-semibold text-ink-soft"><span class="size-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $label }} · {{ $days }}d</p>
                <p class="mt-1 font-display text-2xl font-extrabold tabular-nums">@rm($channelSplit[$key]['revenue'])</p>
                <p class="text-xs text-ink-soft">{{ number_format($channelSplit[$key]['orders']) }} {{ Str::plural('order', $channelSplit[$key]['orders']) }} · avg @rm($channelSplit[$key]['average'])</p>
            </div>
        @endforeach
        <span class="flex items-center gap-1 text-sm font-bold text-berry-700">Compare channels <x-icon name="arrow-right" class="size-4" /></span>
        @if ($channelSplit['online']['revenue'] + $channelSplit['counter']['revenue'] > 0)
            <div class="flex h-2.5 gap-0.5 sm:col-span-3" role="img" aria-label="Revenue split: online {{ $onlineShare }}%, counter {{ 100 - $onlineShare }}%">
                @if ($onlineShare > 0)<div class="rounded-full" style="width: {{ $onlineShare }}%; background: var(--color-online)"></div>@endif
                @if ($onlineShare < 100)<div class="rounded-full" style="width: {{ 100 - $onlineShare }}%; background: var(--color-counter)"></div>@endif
            </div>
        @endif
    </a>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        {{-- Forecast --}}
        <section class="card p-5 sm:p-6 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold">Sales forecast</h2>
                    <p class="text-sm text-ink-soft">Last 7 days and the next 7, predicted from same-weekday averages and recent trend.</p>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-ink-soft">
                    <span class="flex items-center gap-1.5"><span class="h-1 w-4 rounded bg-berry-500"></span> Actual</span>
                    <span class="flex items-center gap-1.5"><span class="h-1 w-4 rounded bg-butter-500"></span> Forecast</span>
                    <span class="flex items-center gap-1.5"><span class="h-0 w-4 border-t-2 border-dashed border-mint-500"></span> Model</span>
                </div>
            </div>

            <div class="-mx-2 mt-4 overflow-x-auto sm:mx-0">
                <svg viewBox="0 0 {{ $width }} {{ $height }}" class="min-w-[560px] sm:min-w-0" role="img" aria-label="Actual and forecast sales chart">
                    <defs>
                        <linearGradient id="actualFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="var(--color-berry-500)" stop-opacity=".25" />
                            <stop offset="1" stop-color="var(--color-berry-500)" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    @for ($tick = 0; $tick <= $axisMax; $tick += $tickStep)
                        <line x1="{{ $pad['left'] }}" x2="{{ $width - $pad['right'] }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="stroke-ink/10" stroke-dasharray="{{ $tick === 0 ? '0' : '4 6' }}" />
                        <text x="{{ $pad['left'] - 10 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" class="fill-ink-soft text-[11px]">{{ $tick >= 1000 ? ($tick / 1000).'k' : $tick }}</text>
                    @endfor

                    <rect x="{{ $x($lastPastIndex) }}" y="{{ $pad['top'] }}" width="{{ $x(count($forecast) - 1) - $x($lastPastIndex) }}" height="{{ $plotHeight }}" class="fill-butter-100/60" rx="8" />
                    <text x="{{ $x($lastPastIndex) + 10 }}" y="{{ $pad['top'] + 16 }}" class="fill-butter-600 text-[11px] font-bold tracking-wider">FORECAST</text>

                    <polygon points="{{ $actualArea }}" fill="url(#actualFill)" />
                    <polyline points="{{ $modelLine }}" fill="none" class="stroke-mint-500" stroke-width="2" stroke-dasharray="5 6" />
                    <polyline points="{{ $futureLine }}" fill="none" class="stroke-butter-500" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline points="{{ $actualLine }}" fill="none" class="stroke-berry-500" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    @foreach ($forecast as $index => $point)
                        @php($value = $point['actual'] ?? $point['predicted'])
                        <circle cx="{{ $x($index) }}" cy="{{ $y($value) }}" r="{{ $point['date']->isToday() ? 6 : 4 }}" stroke-width="2.5"
                            class="{{ $point['is_future'] ? 'fill-white stroke-butter-500' : 'fill-white stroke-berry-500' }}">
                            <title>{{ $point['date']->format('D, j M') }} — {{ $point['is_future'] ? 'forecast' : 'actual' }} RM {{ number_format($value, 2) }}</title>
                        </circle>
                        <text x="{{ $x($index) }}" y="{{ $height - 20 }}" text-anchor="middle" class="{{ $point['date']->isToday() ? 'fill-ink font-bold' : 'fill-ink-soft' }} text-[11px]">{{ $point['date']->isToday() ? 'Today' : $point['date']->format('D') }}</text>
                        <text x="{{ $x($index) }}" y="{{ $height - 6 }}" text-anchor="middle" class="fill-ink-soft/70 text-[10px]">{{ $point['date']->format('j/n') }}</text>
                    @endforeach
                </svg>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-paper p-4">
                    <p class="text-xs font-semibold text-ink-soft">Last 7 days</p>
                    <p class="font-display text-xl font-extrabold">@rm($lastWeekTotal)</p>
                </div>
                <div class="rounded-2xl bg-butter-100 p-4">
                    <p class="text-xs font-semibold text-ink-soft">Next 7 days (forecast)</p>
                    <p class="font-display text-xl font-extrabold">@rm($forecastTotal)
                        <span @class(['text-sm', 'text-mint-600' => $forecastChange > 0, 'text-rose-600' => $forecastChange < 0, 'text-ink-soft' => $forecastChange == 0])>{{ $forecastChange > 0 ? '+' : '' }}{{ $forecastChange == 0 ? '0' : $forecastChange }}%</span>
                    </p>
                </div>
            </div>
        </section>

        {{-- Kitchen queue --}}
        <section class="card flex flex-col p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl font-bold">Kitchen queue</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-bold text-berry-700 hover:underline">Board</a>
            </div>
            @if ($kitchenFocus)
                <p class="mt-2 rounded-2xl bg-mint-50 p-3 text-sm text-mint-700"><x-icon name="sparkles" class="inline size-4" /> Prepare <span class="font-bold">{{ $kitchenFocus['units'] }}× {{ $kitchenFocus['product_name'] }}</span> next — it’s in {{ $kitchenFocus['orders'] }} waiting {{ Str::plural('order', $kitchenFocus['orders']) }}.</p>
            @endif
            <ul class="mt-3 flex-1 divide-y divide-ink/5">
                @forelse ($kitchenQueue as $order)
                    <li class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-bold hover:text-berry-700">{{ $order->customerName() }}</a>
                            <p class="truncate text-xs text-ink-soft">{{ $order->code }} · {{ $order->itemCount() }} items · {{ $order->created_at->diffForHumans(short: true) }}</p>
                        </div>
                        @include('admin.orders.partials.advance-button', ['order' => $order])
                    </li>
                @empty
                    <li class="py-10 text-center text-sm text-ink-soft">The queue is clear. ☕</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {{-- Trends --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-display text-xl font-bold">Collection trends</h2>
            <p class="text-sm text-ink-soft">Units sold this week vs last week</p>
            <ul class="mt-5 space-y-4">
                @foreach ($categoryTrends as $trend)
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-bold">{{ $trend['category']->name }}</span>
                            <span @class([
                                'chip',
                                'bg-mint-50 text-mint-700' => $trend['level'] === 'high',
                                'bg-ink/5 text-ink-soft' => $trend['level'] === 'steady',
                                'bg-rose-50 text-rose-700' => $trend['level'] === 'low',
                            ])>{{ $trend['change'] > 0 ? '+' : '' }}{{ round($trend['change']) }}%</span>
                        </div>
                        <div class="mt-1.5 h-2.5 rounded-full bg-ink/5"><div class="h-full rounded-full bg-berry-500" style="width: {{ round($trend['units'] / $maxTrendUnits * 100) }}%"></div></div>
                        <p class="mt-1 text-xs text-ink-soft">{{ $trend['units'] }} units · {{ $trend['advice'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Reorder from supplier --}}
        <section class="card p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-bold">Reorder from agent</h2>
                    <p class="text-xs text-ink-soft">Based on stock left and the last 14 days of sales</p>
                </div>
                <a href="{{ route('admin.products.index', ['stock' => 'low']) }}" class="text-sm font-bold text-berry-700 hover:underline">All</a>
            </div>
            <ul class="mt-4 space-y-3">
                @forelse ($reorderSuggestions as $suggestion)
                    @php($product = $suggestion['product'])
                    <li class="rounded-2xl p-4 ring-1 {{ $suggestion['urgent'] ? 'bg-rose-50 ring-rose-200' : 'bg-paper ring-ink/5' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-bold">{{ $product->name }}</p>
                                <p class="text-xs text-ink-soft">
                                    {{ $product->stock }} left
                                    @if ($suggestion['days_left'] !== null)
                                        · sells {{ $suggestion['daily_sales'] }}/day · lasts ~{{ $suggestion['days_left'] < 1 ? 'under a day' : $suggestion['days_left'].' days' }}
                                    @endif
                                </p>
                            </div>
                            <span @class(['chip shrink-0', 'bg-rose-600 text-white' => $suggestion['urgent'], 'bg-butter-200 text-plum-900' => ! $suggestion['urgent']])>{{ $suggestion['urgent'] ? 'Urgent' : 'Soon' }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.products.restock', $product) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input type="number" name="quantity" value="{{ $suggestion['quantity'] }}" min="1" max="10000" class="input w-24 bg-surface py-2 text-center" aria-label="Quantity received for {{ $product->name }}">
                            @if ($product->hasSizes())
                                <select name="size" class="input w-auto bg-surface py-2 pr-8" aria-label="Size received for {{ $product->name }}">
                                    @foreach ($product->sizeStock() as $code => $count)
                                        <option value="{{ $code }}" @selected($code === array_search(min($product->sizeStock()), $product->sizeStock(), true))>{{ $code }} · {{ $count }} left</option>
                                    @endforeach
                                </select>
                            @endif
                            <button class="btn-dark flex-1 py-2"><x-icon name="box" class="size-4" /> Mark received</button>
                        </form>
                    </li>
                @empty
                    <li class="rounded-2xl bg-mint-50 p-4 text-sm text-mint-700">Every product has at least a week of stock. 🎉</li>
                @endforelse
            </ul>
        </section>

        {{-- Top products --}}
        <section class="card p-5 sm:p-6 lg:col-span-2 xl:col-span-1">
            <h2 class="font-display text-xl font-bold">Top sellers · {{ $days }}d</h2>
            <ol class="mt-4 space-y-3">
                @foreach ($topProducts as $row)
                    <li class="flex items-center gap-3">
                        <span class="w-5 font-display text-lg font-extrabold text-ink/30">{{ $loop->iteration }}</span>
                        <x-product-image :product="$row['product']" class="size-11 shrink-0 rounded-xl" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold">{{ $row['product']->name }}</p>
                            <p class="text-xs text-ink-soft">{{ number_format($row['units']) }} sold</p>
                        </div>
                        <p class="text-sm font-bold">@rm($row['revenue'])</p>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    {{-- Recent orders --}}
    <section data-live="recent-orders" class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between p-5 sm:p-6">
            <h2 class="font-display text-xl font-bold">Latest orders</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm font-bold text-berry-700 hover:underline">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-paper text-left text-xs font-bold text-ink-soft">
                    <tr><th class="px-6 py-3">Customer</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Status</th><th class="px-6 py-3 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-ink/5">
                    @foreach ($recentOrders as $order)
                        <tr class="hover:bg-paper/60">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-full bg-butter-200 text-xs font-extrabold">{{ $order->customerInitials() }}</span>
                                    <span class="font-semibold">{{ $order->customerName() }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-bold text-berry-700 hover:underline">{{ $order->code }}</a> <span class="text-ink-soft">· {{ $order->created_at->diffForHumans() }}</span></td>
                            <td class="px-4 py-3"><x-status-badge :order="$order" /></td>
                            <td class="px-6 py-3 text-right font-bold">@rm($order->total)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
