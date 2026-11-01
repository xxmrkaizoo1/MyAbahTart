<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin dashboard's figures, worked out by the admin panel itself.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = app()->call([app(AdminDashboardController::class), '__invoke'], ['request' => $request])->getData();

        return response()->json([
            'days' => $data['days'],
            'revenue' => $data['revenue'],
            'revenue_change' => $data['revenueChange'],
            'average_order_value' => $data['averageOrderValue'],
            'order_count' => $data['orderCount'],
            'orders_today' => $data['ordersToday'],
            'orders_today_change' => $data['ordersTodayChange'],
            'active_orders' => $data['activeOrders'],
            'channel_split' => $data['channelSplit'],
            'low_stock_products' => ProductResource::collection($data['lowStockProducts']),
            'forecast' => collect($data['forecast'])->map(fn (array $point) => [...$point, 'date' => $point['date']->toDateString()]),
            'category_trends' => $data['categoryTrends']->map(fn (array $trend) => [...$trend, 'category' => $trend['category']->name])->values(),
            'reorder_suggestions' => $data['reorderSuggestions']->map(fn (array $row) => [...$row, 'product' => new ProductResource($row['product'])]),
            'top_products' => $data['topProducts']->map(fn (array $row) => [...$row, 'product' => new ProductResource($row['product'])]),
            'kitchen_focus' => $data['kitchenFocus'],
            'kitchen_queue' => OrderResource::collection($data['kitchenQueue']),
            'revenue_series' => $data['revenueSeries'],
            'order_series' => $data['orderSeries'],
            'recent_orders' => OrderResource::collection($data['recentOrders']),
        ]);
    }
}
