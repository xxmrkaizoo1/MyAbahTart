<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class LiveController extends Controller
{
    /**
     * A small fingerprint of everything the signed-in user can see change, polled by open pages.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Hosts without cron (such as free shared hosting) still release unpaid orders on time.
        if (Cache::add('live:cancel-unpaid-orders', true, 60)) {
            Artisan::call('orders:cancel-unpaid');
        }

        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $orders = Order::query()->unless($isAdmin, fn ($query) => $query->whereBelongsTo($user));
        $refunds = Refund::query()->unless($isAdmin, fn ($query) => $query->whereHas('order', fn ($query) => $query->whereBelongsTo($user)));

        $parts = [
            $orders->clone()->count(),
            $orders->clone()->max('updated_at'),
            $refunds->clone()->count(),
            $refunds->clone()->max('updated_at'),
        ];

        if ($isAdmin) {
            $parts[] = Product::query()->max('updated_at');
        }

        return response()->json([
            'version' => md5(implode('|', $parts)),
            'incoming' => $isAdmin
                ? Order::query()->where('channel', SalesChannel::Online)->where('status', OrderStatus::Received)->count()
                : null,
        ]);
    }
}
