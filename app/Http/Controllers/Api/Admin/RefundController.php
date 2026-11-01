<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Controller;
use App\Http\Resources\RefundResource;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RefundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = app(AdminRefundController::class)->index($request)->getData();

        /** @var LengthAwarePaginator<int, Refund> $refunds */
        $refunds = $data['refunds'];
        // The payout limit depends on every refund already made on the order.
        $refunds->getCollection()->load('order.refunds');

        return response()->json([
            'status' => $data['status']?->value,
            'refunds' => RefundResource::collection($refunds->items()),
            'meta' => ['current_page' => $refunds->currentPage(), 'last_page' => $refunds->lastPage(), 'total' => $refunds->total()],
            'pending_count' => $data['pendingCount'],
            'refunded_this_month' => $data['refundedThisMonth'],
            'refund_count_this_month' => $data['refundCountThisMonth'],
        ]);
    }
}
