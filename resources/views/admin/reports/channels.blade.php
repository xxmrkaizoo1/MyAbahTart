<x-layouts.admin title="Online vs counter" :subtitle="'How online orders and walk-in counter sales compare over the last '.$days.' days. Revenue is after refunds; cancelled orders are left out.'">
    <x-slot:actions>
        <nav class="inline-flex rounded-full bg-surface p-1 ring-1 ring-ink/10" aria-label="Period">
            @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label)
                <a href="{{ route('admin.sales.index', ['days' => $value]) }}" @class([
                    'rounded-full px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition',
                    'bg-plum-900 text-white' => $days === $value,
                    'text-ink-soft hover:text-ink' => $days !== $value,
                ])>{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot:actions>

    {{-- Channel summaries --}}
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ([['Online', 'var(--color-online)', $online, 'Orders placed on the website'], ['Counter', 'var(--color-counter)', $counter, 'Walk-in sales at the counter']] as [$label, $color, $stats, $description])
            <section class="card overflow-hidden">
                <div class="h-1" style="background: {{ $color }}"></div>
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 font-display text-xl font-bold"><span class="size-3 rounded-full" style="background: {{ $color }}"></span>{{ $label }}</h2>
                            <p class="text-xs text-ink-soft">{{ $description }}</p>
                        </div>
                        @if ($stats['revenue_change'] !== null)
                            <span @class([
                                'flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold',
                                'bg-mint-50 text-mint-700' => $stats['revenue_change'] >= 0,
                                'bg-rose-50 text-rose-700' => $stats['revenue_change'] < 0,
                            ])>
                                <x-icon :name="$stats['revenue_change'] >= 0 ? 'trending-up' : 'trending-down'" class="size-3.5" />
                                {{ $stats['revenue_change'] >= 0 ? '+' : '' }}{{ $stats['revenue_change'] }}%
                                <span class="sr-only">versus the previous {{ $days }} days</span>
                            </span>
                        @endif
                    </div>
                    <p class="mt-4 text-sm font-semibold text-ink-soft">Revenue</p>
                    <p class="font-display text-4xl font-extrabold tabular-nums">@rm($stats['revenue'])</p>
                    <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([['Orders', number_format($stats['orders'])], ['Items sold', number_format($stats['items'])], ['Avg. order', 'RM '.number_format($stats['average'], 2)], ['Refunded', 'RM '.number_format($stats['refunded'], 2)]] as [$statLabel, $value])
                            <div class="rounded-2xl bg-paper p-3">
                                <dt class="text-xs font-semibold text-ink-soft">{{ $statLabel }}</dt>
                                <dd class="mt-0.5 font-bold tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        @endforeach
    </div>

    {{-- Share of sales --}}
    <section class="card mt-4 p-5 sm:p-6">
        <h2 class="font-display text-xl font-bold">Share of sales</h2>
        @if ($totalOrders === 0)
            <p class="mt-3 text-sm text-ink-soft">No sales in the last {{ $days }} days yet.</p>
        @else
            <div class="mt-4 space-y-5">
                @foreach ([['Revenue', $online['revenue'], $counter['revenue'], fn ($value) => 'RM '.number_format($value, 2)], ['Orders', $online['orders'], $counter['orders'], fn ($value) => number_format($value)]] as [$metric, $onlineValue, $counterValue, $format])
                    @php
                        $total = max($onlineValue + $counterValue, 0.0001);
                        $onlineShare = round($onlineValue / $total * 100);
                        $counterShare = 100 - $onlineShare;
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <p class="font-semibold">{{ $metric }}</p>
                            <p class="text-ink-soft">Online <span class="font-bold text-ink">{{ $onlineShare }}%</span> · Counter <span class="font-bold text-ink">{{ $counterShare }}%</span></p>
                        </div>
                        <div class="mt-2 flex h-3 gap-0.5" role="img" aria-label="{{ $metric }}: online {{ $format($onlineValue) }} ({{ $onlineShare }}%), counter {{ $format($counterValue) }} ({{ $counterShare }}%)">
                            @if ($onlineShare > 0)
                                <div class="rounded-full" style="width: {{ $onlineShare }}%; background: var(--color-online)" title="Online {{ $format($onlineValue) }}"></div>
                            @endif
                            @if ($counterShare > 0)
                                <div class="rounded-full" style="width: {{ $counterShare }}%; background: var(--color-counter)" title="Counter {{ $format($counterValue) }}"></div>
                            @endif
                        </div>
                        <div class="mt-1.5 flex justify-between text-xs text-ink-soft tabular-nums">
                            <span>{{ $format($onlineValue) }}</span><span>{{ $format($counterValue) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Daily revenue --}}
    <section class="card mt-4 p-5 sm:p-6">
        <h2 class="font-display text-xl font-bold">Daily revenue</h2>
        <p class="text-sm text-ink-soft">Net revenue each day, by channel. Hover the chart for exact amounts.</p>
        <div class="mt-4">@include('admin.reports.partials.channel-chart', ['daily' => $daily])</div>
    </section>

    {{-- Best sellers --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach ([['Online', 'var(--color-online)', $topOnline], ['Counter', 'var(--color-counter)', $topCounter]] as [$label, $color, $rows])
            <section class="card p-5 sm:p-6">
                <h2 class="flex items-center gap-2 font-display text-xl font-bold"><span class="size-3 rounded-full" style="background: {{ $color }}"></span>Best sellers · {{ $label }}</h2>
                @if ($rows->isEmpty())
                    <p class="mt-4 text-sm text-ink-soft">No {{ strtolower($label) }} sales in this period.</p>
                @else
                    @php($topUnits = max(1, $rows->max('units')))
                    <ol class="mt-4 space-y-3">
                        @foreach ($rows as $row)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate font-semibold"><span class="mr-1.5 text-ink-soft">{{ $loop->iteration }}.</span>{{ $row['product']->name }}</span>
                                    <span class="shrink-0 text-ink-soft tabular-nums"><span class="font-bold text-ink">{{ $row['units'] }}</span> sold · @rm($row['revenue'])</span>
                                </div>
                                <div class="mt-1.5 h-2 rounded-full bg-ink/5"><div class="h-full rounded-full" style="width: {{ round($row['units'] / $topUnits * 100) }}%; background: {{ $color }}"></div></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endforeach
    </div>

    {{-- How customers bought --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach ([['How online orders were delivered', 'var(--color-online)', $deliveries], ['How counter customers paid', 'var(--color-counter)', $payments]] as [$title, $color, $rows])
            <section class="card p-5 sm:p-6">
                <h2 class="font-display text-xl font-bold">{{ $title }}</h2>
                @php($topRevenue = max(0.01, $rows->max('revenue')))
                <ul class="mt-4 space-y-3">
                    @foreach ($rows as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ $row['method']->label() }}</span>
                                <span class="text-ink-soft tabular-nums"><span class="font-bold text-ink">{{ $row['orders'] }}</span> {{ Str::plural('order', $row['orders']) }} · @rm($row['revenue'])</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-ink/5"><div class="h-full rounded-full" style="width: {{ round($row['revenue'] / $topRevenue * 100) }}%; background: {{ $color }}"></div></div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
