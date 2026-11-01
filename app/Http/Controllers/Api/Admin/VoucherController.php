<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    public function index(): JsonResponse
    {
        $data = app(AdminVoucherController::class)->index()->getData();

        return response()->json([
            'vouchers' => $data['vouchers']->map(fn (Voucher $voucher) => [
                ...(new VoucherResource($voucher))->resolve(),
                'uses' => (int) ($data['usage'][$voucher->code]->uses ?? 0),
                'discount_total' => round((float) ($data['usage'][$voucher->code]->discount_total ?? 0), 2),
            ]),
        ]);
    }
}
