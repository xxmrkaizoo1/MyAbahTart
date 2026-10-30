<?php

namespace App\Http\Controllers;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Cart;
use App\Services\ShopInsights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, ShopInsights $insights): View
    {
        $orders = $request->user()->orders()->with('items.product')->latest()->get();
        $activeOrders = $orders->filter(fn (Order $order) => $order->status->isActive())->values();

        return view('shop.orders.index', [
            'activeOrders' => $activeOrders,
            'pastOrders' => $orders->reject(fn (Order $order) => $order->status->isActive())->values(),
            'estimates' => $activeOrders->mapWithKeys(fn (Order $order) => [$order->id => $insights->estimatedReadyAt($order)]),
        ]);
    }

    public function show(Request $request, Order $order, ShopInsights $insights): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['items.product', 'refunds.items.orderItem']);

        return view('shop.orders.show', [
            'order' => $order,
            'estimate' => $insights->estimatedReadyAt($order),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->status !== OrderStatus::Received) {
            return back()->with('error', 'Orders already being prepared can’t be cancelled.');
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', Rule::enum(CancellationReason::class)->only(CancellationReason::forCustomers())],
            'cancellation_note' => ['required_if:cancellation_reason,other', 'nullable', 'string', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Tell us why you’re cancelling.',
            'cancellation_reason.enum' => 'Tell us why you’re cancelling.',
            'cancellation_note.required_if' => 'Tell us a little more about why you’re cancelling.',
        ]);

        DB::transaction(function () use ($order, $validated) {
            foreach ($order->items as $item) {
                $item->product?->adjustStock($item->size, $item->quantity);
            }

            $order->fill([
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancellation_note' => $validated['cancellation_note'] ?? null,
            ]);
            $order->transitionTo(OrderStatus::Cancelled);
        });

        return back()->with('status', "Order {$order->code} has been cancelled.");
    }

    public function reorder(Request $request, Order $order, Cart $cart): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $added = 0;

        foreach ($order->items()->with('product')->get() as $item) {
            if ($item->product?->is_active && $item->product->offersSize($item->size) && $item->product->stockFor($item->size) > 0) {
                $added += $cart->add($item->product, $item->quantity, $item->size) > 0 ? 1 : 0;
            }
        }

        return $added > 0
            ? redirect()->route('cart.index')->with('status', 'Items from your past order are back in your cart.')
            : back()->with('error', 'Sorry, the items in this order are no longer available.');
    }
}
