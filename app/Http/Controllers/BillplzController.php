<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Services\Billplz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillplzController extends Controller
{
    /**
     * The customer comes back from the Billplz payment page.
     */
    public function return(Request $request, Billplz $billplz): RedirectResponse
    {
        $fields = (array) $request->query('billplz', []);
        abort_unless($billplz->hasValidSignature($fields, 'billplz'), 403);

        $order = Order::query()->where('billplz_bill_id', (string) ($fields['id'] ?? ''))->firstOrFail();

        try {
            $paid = $billplz->confirm($order);
        } catch (PaymentGatewayException) {
            $paid = false;
        }

        return redirect()->route('orders.show', $order)->with(
            $paid ? 'status' : 'error',
            $paid
                ? "Payment received. Thank you! Order {$order->code} is with our team."
                : 'Your payment wasn’t completed. You can try again below.',
        );
    }

    /**
     * Billplz tells the shop, server to server, that a bill has changed.
     */
    public function callback(Request $request, Billplz $billplz): Response
    {
        abort_unless($billplz->hasValidSignature($request->post()), 403);

        $order = Order::query()->where('billplz_bill_id', (string) $request->input('id'))->first();

        if ($order && $request->input('paid') === 'true') {
            $billplz->confirm($order);
        }

        return response('OK');
    }
}
