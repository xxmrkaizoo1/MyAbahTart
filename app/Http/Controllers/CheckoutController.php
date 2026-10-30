<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Exceptions\DeliveryQuoteException;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use App\Services\Lalamove;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, Cart $cart, Lalamove $lalamove): RedirectResponse
    {
        if ($cart->lines()->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $deliveryMethod = $request->deliveryMethod();
        $quotedFee = null;

        if ($deliveryMethod === DeliveryMethod::Express && $lalamove->isLive()) {
            try {
                $quotedFee = $lalamove->quote($request->validated('address'))->price;
            } catch (DeliveryQuoteException $exception) {
                throw ValidationException::withMessages(['address' => $exception->getMessage()]);
            }
        }

        $summary = $cart->summary($deliveryMethod, $quotedFee);

        $order = DB::transaction(function () use ($request, $cart, $deliveryMethod, $summary) {
            $products = Product::query()
                ->whereKey($cart->lines()->pluck('product.id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart->lines() as $line) {
                $product = $products[$line['product']->id];
                $available = $product->stockFor($line['size']);

                if ($available < $line['quantity']) {
                    $name = $line['size'] === null ? $product->name : "{$product->name} ({$line['size']->label()})";

                    throw ValidationException::withMessages([
                        'cart' => "Sorry, only {$available} of {$name} left. Please update your cart.",
                    ]);
                }
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'delivery_method' => $deliveryMethod,
                'phone' => $request->validated('phone'),
                'address' => $deliveryMethod->requiresAddress() ? $request->validated('address') : null,
                'notes' => $request->validated('notes'),
                'subtotal' => $summary['subtotal'],
                'delivery_fee' => $summary['delivery_fee'],
                'discount' => $summary['discount'],
                'total' => $summary['total'],
                'voucher_code' => $summary['discount'] > 0 ? $summary['voucher']?->code : null,
            ]);

            foreach ($cart->lines() as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'size' => $line['size'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                $products[$line['product']->id]->adjustStock($line['size'], -$line['quantity']);
            }

            return $order;
        });

        $cart->clear();

        if ($request->user()->address === null && $order->address !== null) {
            $request->user()->update(['address' => $order->address]);
        }

        return redirect()->route('orders.show', $order)
            ->with('status', "Thank you! Order {$order->code} has been placed.");
    }
}
