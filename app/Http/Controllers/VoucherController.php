<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
        ]);

        $voucher = Voucher::findByCode($validated['code']);

        if ($voucher === null || ! $voucher->isUsable()) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.'])->withInput();
        }

        if ($cart->subtotal() < (float) $voucher->min_subtotal) {
            return back()->withErrors([
                'code' => 'This code needs a minimum spend of RM '.number_format((float) $voucher->min_subtotal, 2).'.',
            ])->withInput();
        }

        $cart->applyVoucher($voucher);

        return back()->with('status', "{$voucher->code} applied — {$voucher->description}.");
    }

    public function destroy(Cart $cart): RedirectResponse
    {
        $cart->forgetVoucher();

        return back();
    }
}
