<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sales figures split between online orders and walk-in counter sales.
 */
class ChannelSales
{
    /**
     * Headline figures for one channel over the last given days, compared with the days before.
     *
     * @return array{revenue: float, refunded: float, orders: int, items: int, average: float, revenue_change: float|null, orders_change: float|null}
     */
    public function summary(SalesChannel $channel, int $days): array
    {
        $currentFrom = now()->subDays($days);
        $previousFrom = now()->subDays($days * 2);

        $current = $this->figures($channel, $currentFrom, now());
        $previous = $this->figures($channel, $previousFrom, $currentFrom);

        return [
            ...$current,
            'average' => $current['orders'] > 0 ? round($current['revenue'] / $current['orders'], 2) : 0.0,
            'revenue_change' => $this->percentageChange($current['revenue'], $previous['revenue']),
            'orders_change' => $this->percentageChange($current['orders'], $previous['orders']),
        ];
    }

    /**
     * Net revenue per day for each channel, oldest first.
     *
     * @return array<string, array{online: float, counter: float}> Keyed by Y-m-d date.
     */
    public function dailyRevenue(int $days): array
    {
        $until = now()->endOfDay();
        $from = $until->copy()->subDays($days - 1)->startOfDay();

        $totals = Order::query()
            ->notCancelled()
            ->whereBetween('created_at', [$from, $until])
            ->get(['id', 'total', 'channel', 'created_at']);

        $refunds = Refund::query()
            ->approved()
            ->whereIn('order_id', $totals->pluck('id'))
            ->get(['order_id', 'amount'])
            ->groupBy('order_id')
            ->map(fn (Collection $refunds) => (float) $refunds->sum('amount'));

        $series = [];

        for ($date = $from->copy(); $date->lte($until); $date->addDay()) {
            $series[$date->toDateString()] = ['online' => 0.0, 'counter' => 0.0];
        }

        foreach ($totals as $order) {
            $day = $order->created_at->toDateString();
            $series[$day][$order->channel->value] += (float) $order->total - ($refunds[$order->id] ?? 0.0);
        }

        return array_map(fn (array $day) => array_map(fn (float $value) => round($value, 2), $day), $series);
    }

    /**
     * Best sellers for one channel by units sold.
     *
     * @return Collection<int, array{product: Product, units: int, revenue: float}>
     */
    public function topProducts(SalesChannel $channel, int $days, int $limit = 5): Collection
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.channel', $channel->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', now()->subDays($days))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->orderByDesc('units')
            ->limit($limit)
            ->toBase()
            ->get();

        $products = Product::query()->whereKey($rows->pluck('product_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($row) => $products->has($row->product_id))
            ->map(fn ($row) => [
                'product' => $products[$row->product_id],
                'units' => (int) $row->units,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values();
    }

    /**
     * How counter customers paid, largest first.
     *
     * @return Collection<int, array{method: PaymentMethod, orders: int, revenue: float}>
     */
    public function counterPayments(int $days): Collection
    {
        $rows = $this->ordersFor(SalesChannel::Counter, now()->subDays($days), now())
            ->toBase()
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as order_count, SUM(total) as revenue')
            ->get()
            ->keyBy('payment_method');

        return collect(PaymentMethod::forCounter())
            ->map(fn (PaymentMethod $method) => [
                'method' => $method,
                'orders' => (int) ($rows[$method->value]->order_count ?? 0),
                'revenue' => round((float) ($rows[$method->value]->revenue ?? 0), 2),
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * How online customers received their orders, largest first.
     *
     * @return Collection<int, array{method: DeliveryMethod, orders: int, revenue: float}>
     */
    public function onlineDeliveries(int $days): Collection
    {
        $rows = $this->ordersFor(SalesChannel::Online, now()->subDays($days), now())
            ->toBase()
            ->groupBy('delivery_method')
            ->selectRaw('delivery_method, COUNT(*) as order_count, SUM(total) as revenue')
            ->get()
            ->keyBy('delivery_method');

        return collect(DeliveryMethod::cases())
            ->map(fn (DeliveryMethod $method) => [
                'method' => $method,
                'orders' => (int) ($rows[$method->value]->order_count ?? 0),
                'revenue' => round((float) ($rows[$method->value]->revenue ?? 0), 2),
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * @return array{revenue: float, refunded: float, orders: int, items: int}
     */
    private function figures(SalesChannel $channel, Carbon $from, Carbon $until): array
    {
        $orders = $this->ordersFor($channel, $from, $until);

        $gross = (float) (clone $orders)->sum('total');
        $refunded = (float) Refund::query()->approved()->whereIn('order_id', (clone $orders)->select('id'))->sum('amount');

        return [
            'revenue' => round($gross - $refunded, 2),
            'refunded' => round($refunded, 2),
            'orders' => (clone $orders)->count(),
            'items' => (int) OrderItem::query()->whereIn('order_id', (clone $orders)->select('id'))->sum('quantity'),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function ordersFor(SalesChannel $channel, Carbon $from, Carbon $until): Builder
    {
        return Order::query()
            ->notCancelled()
            ->where('channel', $channel->value)
            ->whereBetween('created_at', [$from, $until]);
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
