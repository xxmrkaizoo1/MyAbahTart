<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\SalesReportController as AdminSalesReportController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Online orders and counter sales, side by side.
 */
class SalesReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = app()->call([app(AdminSalesReportController::class), '__invoke'], ['request' => $request])->getData();

        $withProduct = fn (array $row) => [...$row, 'product' => new ProductResource($row['product'])];
        $withMethod = fn (array $row) => [...$row, 'method' => $row['method']->value, 'label' => $row['method']->label()];

        return response()->json([
            'days' => $data['days'],
            'online' => $data['online'],
            'counter' => $data['counter'],
            'total_revenue' => $data['totalRevenue'],
            'total_orders' => $data['totalOrders'],
            'daily' => collect($data['daily'])->map(fn (array $day, string $date) => ['date' => $date, ...$day])->values(),
            'top_online' => $data['topOnline']->map($withProduct),
            'top_counter' => $data['topCounter']->map($withProduct),
            'deliveries' => $data['deliveries']->map($withMethod),
            'payments' => $data['payments']->map($withMethod),
        ]);
    }
}
