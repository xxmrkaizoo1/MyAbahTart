<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Services\ChannelSales;
use App\Services\ShopInsights;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ShopInsights $insights, ChannelSales $channelSales): View
    {
        $days = in_array((int) $request->query('days'), [7, 30], true) ? (int) $request->query('days') : 7;

        $currentFrom = now()->subDays($days);
        $previousFrom = now()->subDays($days * 2);

        $revenue = (float) Order::query()->notCancelled()->where('created_at', '>=', $currentFrom)->sum('total')
            - $this->refundedOn(fn ($orders) => $orders->where('created_at', '>=', $currentFrom));
        $previousRevenue = (float) Order::query()->notCancelled()->whereBetween('created_at', [$previousFrom, $currentFrom])->sum('total')
            - $this->refundedOn(fn ($orders) => $orders->whereBetween('created_at', [$previousFrom, $currentFrom]));
        $orderCount = Order::query()->notCancelled()->where('created_at', '>=', $currentFrom)->count();

        $ordersToday = Order::query()->notCancelled()->whereDate('created_at', today())->count();
        $ordersYesterday = Order::query()->notCancelled()->whereDate('created_at', today()->subDay())->count();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', $currentFrom)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $topProductModels = Product::query()->with('category')->whereKey($topProducts->pluck('product_id'))->get()->keyBy('id');

        return view('admin.dashboard', [
            'days' => $days,
            'revenue' => $revenue,
            'revenueChange' => $this->percentageChange($revenue, $previousRevenue),
            'averageOrderValue' => $orderCount > 0 ? $revenue / $orderCount : 0,
            'orderCount' => $orderCount,
            'ordersToday' => $ordersToday,
            'ordersTodayChange' => $this->percentageChange($ordersToday, $ordersYesterday),
            'activeOrders' => Order::query()->active()->count(),
            'channelSplit' => [
                'online' => $channelSales->summary(SalesChannel::Online, $days),
                'counter' => $channelSales->summary(SalesChannel::Counter, $days),
            ],
            'lowStockProducts' => Product::query()->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->get(),
            'forecast' => $insights->salesForecast(),
            'categoryTrends' => $insights->categoryTrends()->take(4),
            'reorderSuggestions' => $insights->reorderSuggestions(),
            'topProducts' => $topProducts->map(fn ($row) => [
                'product' => $topProductModels[$row->product_id] ?? null,
                'units' => (int) $row->units,
                'revenue' => (float) $row->revenue,
            ])->filter(fn (array $row) => $row['product'] !== null)->values(),
            'kitchenFocus' => $insights->kitchenFocus(),
            'kitchenQueue' => Order::query()->active()->with(['user', 'items'])->oldest()->take(6)->get(),
            'revenueSeries' => array_values($insights->dailyRevenue($days)),
            'orderSeries' => array_values($insights->dailyOrderCounts($days)),
            'recentOrders' => Order::query()->with('user')->latest()->take(6)->get(),
        ]);
    }

    /**
     * Money refunded on the orders matched by the given constraint.
     *
     * @param  Closure(Builder<Order>): mixed  $orders
     */
    private function refundedOn(Closure $orders): float
    {
        return (float) Refund::query()->approved()->whereHas('order', $orders)->sum('amount');
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
