{{-- Daily net revenue, online vs counter, on one shared axis. Expects $daily: array<Y-m-d, {online, counter}>. --}}
@php
    $dates = array_keys($daily);
    $count = count($dates);
    $peak = max(1, ...array_map(fn (array $day) => max($day['online'], $day['counter']), array_values($daily)));
    $magnitude = 10 ** floor(log10($peak));
    $niceMax = collect([1, 2, 2.5, 5, 10])->map(fn ($step) => $step * $magnitude)->first(fn ($candidate) => $candidate >= $peak);
    $ticks = collect(range(0, 4))->map(fn ($index) => $niceMax / 4 * $index);
    $x = fn (int $index) => $count > 1 ? round($index / ($count - 1) * 100, 3) : 50;
    $y = fn (float $value) => round(100 - $value / $niceMax * 100, 3);
    $line = fn (string $channel) => collect($dates)->map(fn ($date, $index) => $x($index).','.$y($daily[$date][$channel]))->implode(' ');
    $labelEvery = max(1, (int) ceil($count / 6));
    $last = $daily[end($dates)];
    $series = [
        'online' => ['label' => 'Online', 'color' => 'var(--color-online)'],
        'counter' => ['label' => 'Counter', 'color' => 'var(--color-counter)'],
    ];
    $chartPoints = collect($daily)->map(fn (array $day, string $date) => [
        'date' => \Illuminate\Support\Carbon::parse($date)->format('D, j M'),
        'online' => $day['online'],
        'counter' => $day['counter'],
    ])->values();
@endphp

<figure data-channel-chart data-points="{{ $chartPoints->toJson() }}">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-hidden="true">
        @foreach ($series as $key => $meta)
            <span class="flex items-center gap-2 text-ink-soft"><span class="h-0.5 w-5 rounded-full" style="background: {{ $meta['color'] }}"></span>{{ $meta['label'] }}</span>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-[auto_1fr] gap-x-3 sm:pr-16">
        {{-- Y axis --}}
        <div class="relative h-56 w-14 text-right text-[11px] text-ink-soft tabular-nums" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span class="absolute right-0 -translate-y-1/2" style="top: {{ $y($tick) }}%">{{ $tick >= 1000 ? 'RM'.rtrim(rtrim(number_format($tick / 1000, 1), '0'), '.').'k' : 'RM'.number_format($tick) }}</span>
            @endforeach
        </div>

        {{-- Plot --}}
        <div data-chart-plot class="relative h-56 cursor-crosshair">
            <svg class="absolute inset-0 size-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" role="img"
                aria-label="Daily revenue for the last {{ $count }} days. Online ended at RM {{ number_format($last['online'], 2) }} and counter at RM {{ number_format($last['counter'], 2) }} on the last day.">
                @foreach ($ticks as $tick)
                    <line x1="0" x2="100" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" vector-effect="non-scaling-stroke" @class(['stroke-ink/10' => $tick > 0, 'stroke-ink/25' => $tick == 0]) stroke-width="1" />
                @endforeach
                @foreach ($series as $key => $meta)
                    <polyline points="{{ $line($key) }}" fill="none" stroke="{{ $meta['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                @endforeach
            </svg>

            {{-- Direct labels at the end of each line, nudged apart if they would overlap. --}}
            @php
                $onlineTop = $y($last['online']);
                $counterTop = $y($last['counter']);
                if (abs($onlineTop - $counterTop) < 9) {
                    $shift = (9 - abs($onlineTop - $counterTop)) / 2;
                    [$onlineTop, $counterTop] = $onlineTop <= $counterTop ? [$onlineTop - $shift, $counterTop + $shift] : [$onlineTop + $shift, $counterTop - $shift];
                }
            @endphp
            @foreach (['online' => $onlineTop, 'counter' => $counterTop] as $key => $top)
                <span class="pointer-events-none absolute right-0 hidden -translate-y-1/2 translate-x-[calc(100%+8px)] text-xs font-semibold text-ink sm:block" style="top: {{ max(0, min(100, $top)) }}%" aria-hidden="true">{{ $series[$key]['label'] }}</span>
            @endforeach

            {{-- Hover layer: crosshair, markers and tooltip, positioned by app.js. --}}
            <div data-chart-crosshair class="pointer-events-none absolute inset-y-0 hidden w-px bg-ink/30"></div>
            @foreach ($series as $key => $meta)
                <span data-chart-marker="{{ $key }}" class="pointer-events-none absolute hidden size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-surface" style="background: {{ $meta['color'] }}"></span>
            @endforeach
            <div data-chart-tooltip role="status" class="pointer-events-none absolute top-2 z-10 hidden min-w-40 rounded-2xl bg-surface p-3 text-xs shadow-pop ring-1 ring-ink/10">
                <p data-chart-tooltip-date class="font-bold text-ink"></p>
                @foreach ($series as $key => $meta)
                    <p class="mt-1.5 flex items-center justify-between gap-4 text-ink-soft">
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: {{ $meta['color'] }}"></span>{{ $meta['label'] }}</span>
                        <span data-chart-tooltip-value="{{ $key }}" class="font-bold text-ink tabular-nums"></span>
                    </p>
                @endforeach
            </div>
        </div>

        {{-- X axis --}}
        <div></div>
        <div class="relative mt-2 h-4 text-[11px] text-ink-soft" aria-hidden="true">
            @foreach ($dates as $index => $date)
                @if ($index % $labelEvery === 0 || $index === $count - 1)
                    @continue($index !== $count - 1 && $count - 1 - $index < $labelEvery / 2)
                    <span @class(['absolute whitespace-nowrap', '-translate-x-1/2' => $index > 0 && $index < $count - 1, '-translate-x-full' => $index === $count - 1])
                        style="left: {{ $x($index) }}%">{{ \Illuminate\Support\Carbon::parse($date)->format('j M') }}</span>
                @endif
            @endforeach
        </div>
    </div>

    <details class="group mt-4">
        <summary class="cursor-pointer list-none text-sm font-semibold text-berry-700 hover:underline">
            <span class="group-open:hidden">View as table</span><span class="hidden group-open:inline">Hide table</span>
        </summary>
        <div class="mt-3 max-h-72 overflow-auto rounded-2xl ring-1 ring-ink/10">
            <table class="w-full text-sm">
                <caption class="sr-only">Daily net revenue by channel</caption>
                <thead class="sticky top-0 bg-paper text-left text-xs text-ink-soft">
                    <tr><th scope="col" class="px-4 py-2 font-semibold">Day</th><th scope="col" class="px-4 py-2 text-right font-semibold">Online</th><th scope="col" class="px-4 py-2 text-right font-semibold">Counter</th><th scope="col" class="px-4 py-2 text-right font-semibold">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-ink/5 tabular-nums">
                    @foreach (array_reverse($daily, true) as $date => $day)
                        <tr>
                            <th scope="row" class="px-4 py-2 text-left font-medium">{{ \Illuminate\Support\Carbon::parse($date)->format('D, j M') }}</th>
                            <td class="px-4 py-2 text-right">@rm($day['online'])</td>
                            <td class="px-4 py-2 text-right">@rm($day['counter'])</td>
                            <td class="px-4 py-2 text-right font-semibold">@rm($day['online'] + $day['counter'])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
