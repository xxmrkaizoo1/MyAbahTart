<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data-driven suggestions and forecasts built from the shop's own order history.
 */
class ShopInsights
{
    /**
     * Products ranked by units sold within the last given days.
     *
     * @return Collection<int, int> Units sold keyed by product id.
     */
    public function unitsSoldByProduct(int $days, ?Carbon $until = null): Collection
    {
        $until ??= now();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.created_at', [$until->copy()->subDays($days), $until])
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units')
            ->orderByDesc('units')
            ->pluck('units', 'product_id')
            ->map(fn ($units) => (int) $units);
    }

    /**
     * @return Collection<int, Product>
     */
    public function popularProducts(int $limit = 4, int $days = 30): Collection
    {
        $ranking = $this->unitsSoldByProduct($days);

        $products = Product::query()->available()->with('category')->withAvg('reviews', 'rating')->get();

        return $products
            ->sortByDesc(fn (Product $product) => $ranking[$product->id] ?? -1)
            ->take($limit)
            ->values();
    }

    /**
     * Personal suggestions based on the categories the customer orders most.
     *
     * @return Collection<int, array{product: Product, reason: string}>
     */
    public function recommendationsFor(?User $user, int $limit = 4): Collection
    {
        $favouriteCategoryIds = $user ? OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.user_id', $user->id)
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, SUM(order_items.quantity) as units')
            ->orderByDesc('units')
            ->limit(2)
            ->pluck('category_id') : collect();

        if ($favouriteCategoryIds->isEmpty()) {
            return $this->popularProducts($limit, 14)->map(fn (Product $product) => [
                'product' => $product,
                'reason' => 'Trending this week',
            ]);
        }

        $ranking = $this->unitsSoldByProduct(30);

        $suggestions = Product::query()
            ->available()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->whereIn('category_id', $favouriteCategoryIds)
            ->get()
            ->sortByDesc(fn (Product $product) => $ranking[$product->id] ?? -1)
            ->take($limit)
            ->map(fn (Product $product) => [
                'product' => $product,
                'reason' => 'Because you love '.$product->category->name,
            ]);

        if ($suggestions->count() < $limit) {
            $suggestions = $suggestions->concat(
                $this->popularProducts($limit * 2, 30)
                    ->reject(fn (Product $product) => $suggestions->contains(fn ($suggestion) => $suggestion['product']->is($product)))
                    ->take($limit - $suggestions->count())
                    ->map(fn (Product $product) => ['product' => $product, 'reason' => 'Popular this month'])
            );
        }

        return $suggestions->values();
    }

    /**
     * Products that customers most often buy in the same order as the given product.
     *
     * @return Collection<int, Product>
     */
    public function boughtTogether(Product $product, int $limit = 3): Collection
    {
        $orderIds = OrderItem::query()->where('product_id', $product->id)->select('order_id');

        $companionIds = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $product->id)
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) as times')
            ->orderByDesc('times')
            ->limit($limit)
            ->pluck('product_id');

        $companions = Product::query()->available()->whereKey($companionIds)->get();

        return $companionIds
            ->map(fn (int $id) => $companions->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    /**
     * Revenue for each of the last given days, oldest first.
     *
     * @return array<string, float> Revenue keyed by Y-m-d date.
     */
    public function dailyRevenue(int $days, ?Carbon $until = null): array
    {
        $until = ($until ?? now())->copy()->endOfDay();
        $from = $until->copy()->subDays($days - 1)->startOfDay();

        $totals = Order::query()
            ->notCancelled()
            ->whereBetween('created_at', [$from, $until])
            ->get(['total', 'created_at'])
            ->groupBy(fn (Order $order) => $order->created_at->toDateString())
            ->map(fn (Collection $orders) => round((float) $orders->sum('total'), 2));

        $series = [];

        for ($date = $from->copy(); $date->lte($until); $date->addDay()) {
            $series[$date->toDateString()] = $totals[$date->toDateString()] ?? 0.0;
        }

        return $series;
    }

    /**
     * Number of orders placed on each of the last given days, oldest first.
     *
     * @return array<string, int> Order counts keyed by Y-m-d date.
     */
    public function dailyOrderCounts(int $days): array
    {
        $until = now()->endOfDay();
        $from = $until->copy()->subDays($days - 1)->startOfDay();

        $counts = Order::query()
            ->notCancelled()
            ->whereBetween('created_at', [$from, $until])
            ->get(['created_at'])
            ->countBy(fn (Order $order) => $order->created_at->toDateString());

        $series = [];

        for ($date = $from->copy(); $date->lte($until); $date->addDay()) {
            $series[$date->toDateString()] = (int) ($counts[$date->toDateString()] ?? 0);
        }

        return $series;
    }

    /**
     * Last week's actual sales next to what the model would have predicted, plus a forecast for the coming week.
     *
     * The prediction for a day is the average of the same weekday over the previous four weeks,
     * scaled by the recent week-over-week trend.
     *
     * @return list<array{date: Carbon, actual: ?float, predicted: float, is_future: bool}>
     */
    public function salesForecast(): array
    {
        $history = $this->dailyRevenue(42);
        $dates = array_keys($history);
        $values = array_values($history);
        $count = count($values);

        $lastWeek = array_sum(array_slice($values, -7));
        $previousWeek = array_sum(array_slice($values, -14, 7));
        $trend = $previousWeek > 0 ? max(0.8, min(1.25, $lastWeek / $previousWeek)) : 1.0;

        $predictFrom = function (int $index) use ($values, $trend): float {
            $sameWeekday = [];

            for ($weeksBack = 1; $weeksBack <= 4; $weeksBack++) {
                if (isset($values[$index - 7 * $weeksBack])) {
                    $sameWeekday[] = $values[$index - 7 * $weeksBack];
                }
            }

            return $sameWeekday === [] ? 0.0 : round(array_sum($sameWeekday) / count($sameWeekday) * $trend, 2);
        };

        $points = [];

        for ($index = $count - 7; $index < $count; $index++) {
            $points[] = [
                'date' => Carbon::parse($dates[$index]),
                'actual' => $values[$index],
                'predicted' => $predictFrom($index),
                'is_future' => false,
            ];
        }

        for ($offset = 1; $offset <= 7; $offset++) {
            $points[] = [
                'date' => Carbon::parse(end($dates))->addDays($offset),
                'actual' => null,
                'predicted' => $predictFrom($count - 1 + $offset),
                'is_future' => true,
            ];
        }

        return $points;
    }

    /**
     * Compare each category's units sold this week with the week before.
     *
     * @return Collection<int, array{category: Category, units: int, change: float, level: string, advice: string}>
     */
    public function categoryTrends(): Collection
    {
        $unitsByCategory = function (CarbonInterface $from, CarbonInterface $until): Collection {
            return OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->whereBetween('orders.created_at', [$from, $until])
                ->groupBy('products.category_id')
                ->selectRaw('products.category_id, SUM(order_items.quantity) as units')
                ->pluck('units', 'category_id')
                ->map(fn ($units) => (int) $units);
        };

        $thisWeek = $unitsByCategory(now()->subDays(7), now());
        $lastWeek = $unitsByCategory(now()->subDays(14), now()->subDays(7));

        return Category::query()->orderBy('sort_order')->get()
            ->map(function (Category $category) use ($thisWeek, $lastWeek) {
                $current = $thisWeek[$category->id] ?? 0;
                $previous = $lastWeek[$category->id] ?? 0;
                $change = $previous > 0 ? ($current - $previous) / $previous * 100 : ($current > 0 ? 100.0 : 0.0);

                [$level, $advice] = match (true) {
                    $change >= 15 => ['high', 'Up '.round($change).'% — plan extra batches next week.'],
                    $change <= -15 => ['low', 'Down '.abs(round($change)).'% — try a bundle promo or prepare less.'],
                    default => ['steady', 'Steady demand — keep the current preparation plan.'],
                };

                return [
                    'category' => $category,
                    'units' => $current,
                    'change' => round($change, 1),
                    'level' => $level,
                    'advice' => $advice,
                ];
            })
            ->sortByDesc('units')
            ->values();
    }

    /**
     * Products to reorder from the supplier, based on stock left and the last two weeks of sales.
     *
     * A product is flagged when it is at or below its low-stock threshold or will sell out
     * within a week. The suggested quantity covers two weeks of sales.
     *
     * @return Collection<int, array{product: Product, daily_sales: float, days_left: float|null, quantity: int, urgent: bool}>
     */
    public function reorderSuggestions(int $limit = 4): Collection
    {
        $unitsSold = $this->unitsSoldByProduct(14);

        return Product::query()
            ->where('is_active', true)
            ->get()
            ->map(function (Product $product) use ($unitsSold): array {
                $dailySales = ($unitsSold[$product->id] ?? 0) / 14;
                $daysLeft = $dailySales > 0 ? $product->stock / $dailySales : null;
                $target = max((int) ceil($dailySales * 14), $product->low_stock_threshold * 2, 5);

                return [
                    'product' => $product,
                    'daily_sales' => round($dailySales, 1),
                    'days_left' => $daysLeft === null ? null : round($daysLeft, 1),
                    'quantity' => (int) (ceil(max($target - $product->stock, 1) / 5) * 5),
                    'urgent' => $product->stock === 0 || ($daysLeft !== null && $daysLeft <= 2),
                ];
            })
            ->filter(fn (array $row) => $row['product']->isLowStock() || ($row['days_left'] !== null && $row['days_left'] <= 7))
            ->sortBy(fn (array $row) => $row['days_left'] ?? ($row['product']->stock === 0 ? -1 : PHP_INT_MAX))
            ->take($limit)
            ->values();
    }

    /**
     * The product needed in the largest quantity across orders still in the kitchen.
     *
     * @return array{product_name: string, units: int, orders: int}|null
     */
    public function kitchenFocus(): ?array
    {
        $row = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [OrderStatus::Received->value, OrderStatus::Preparing->value])
            ->groupBy('order_items.product_name')
            ->select('order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as units, COUNT(DISTINCT orders.id) as orders')
            ->orderByDesc('units')
            ->first();

        return $row ? [
            'product_name' => $row->product_name,
            'units' => (int) $row->units,
            'orders' => (int) $row->orders,
        ] : null;
    }

    /**
     * Estimate when an active order will be ready from the queue ahead of it.
     */
    public function estimatedReadyAt(Order $order): ?Carbon
    {
        if (! in_array($order->status, [OrderStatus::Received, OrderStatus::Preparing], true)) {
            return $order->ready_at;
        }

        $ordersAhead = Order::query()
            ->whereIn('status', [OrderStatus::Received, OrderStatus::Preparing])
            ->where('created_at', '<', $order->created_at)
            ->count();

        $minutes = 20 + min($ordersAhead, 12) * 5 + min($order->itemCount(), 10) * 2;

        return ($order->preparing_at ?? $order->created_at)->copy()->addMinutes($minutes);
    }
}
