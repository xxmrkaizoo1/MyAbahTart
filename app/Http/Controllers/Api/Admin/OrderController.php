<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = app()->call([app(AdminOrderController::class), 'index'], ['request' => $request])->getData();

        /** @var LengthAwarePaginator<int, Order> $orders */
        $orders = $data['orders'];

        return response()->json([
            'orders' => OrderResource::collection($orders->items()),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
            'active_status' => $data['activeStatus']?->value,
            'search' => $data['search'],
            'counts' => (object) $data['counts']->all(),
            'completed_today' => $data['completedToday'],
            'board' => (object) $data['board']->map(fn ($orders) => OrderResource::collection($orders))->all(),
            'kitchen_focus' => $data['kitchenFocus'],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $data = app()->call([app(AdminOrderController::class), 'show'], ['order' => $order])->getData();

        return response()->json([
            'order' => new OrderResource($data['order']),
            'estimated_ready_at' => $data['estimate'],
        ]);
    }
}
