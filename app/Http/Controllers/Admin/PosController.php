<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductSize;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\CounterItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The point-of-sale screen for walk-in customers.
 */
class PosController extends Controller
{
    public function index(Request $request): View
    {
        $todayIds = CounterItem::productIdsOn(today());
        $showAll = $request->boolean('all') || $todayIds === [];

        $todaysSales = Order::query()->atCounter()->notCancelled()->whereDate('created_at', today());

        return view('admin.pos.index', [
            'products' => Product::query()
                ->available()
                ->with('category')
                ->when(! $showAll, fn ($query) => $query->whereKey($todayIds))
                ->orderBy('name')
                ->get(),
            'showAll' => $showAll,
            'broughtOut' => $quantities = CounterItem::quantitiesOn(today()),
            'soldToday' => $sold = CounterItem::soldOn(today()),
            'sizeBroughtOut' => CounterItem::sizeQuantitiesOn(today()),
            'soldBySize' => CounterItem::soldBySizeOn(today()),
            'hasTodayMenu' => $todayIds !== [],
            'salesCount' => (clone $todaysSales)->count(),
            'salesTotal' => (float) (clone $todaysSales)->sum('total'),
            'recentSales' => (clone $todaysSales)->with('items')->latest()->take(5)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.size' => ['nullable', Rule::enum(ProductSize::class)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_tendered' => ['nullable', 'required_if:payment_method,'.PaymentMethod::Cash->value, 'numeric', 'min:0', 'max:99999'],
            'customer_name' => ['nullable', 'string', 'max:100'],
        ], [
            'items.required' => 'Add at least one item to the sale.',
            'amount_tendered.required_if' => 'Enter how much cash the customer gave.',
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $products = Product::query()
                ->whereKey(collect($validated['items'])->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = collect($validated['items'])->map(function (array $line) use ($products): array {
                $product = $products[$line['product_id']] ?? null;
                $size = ProductSize::tryFrom($line['size'] ?? '');

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => 'One of the items is no longer for sale.']);
                }

                if (! $product->offersSize($size)) {
                    throw ValidationException::withMessages(['items' => "Choose a valid size for {$product->name}."]);
                }

                if ($size !== null && (int) $line['quantity'] > $product->stockFor($size)) {
                    throw ValidationException::withMessages(['items' => "Only {$product->stockFor($size)} of {$product->name} ({$size->label()}) in stock."]);
                }

                return ['product' => $product, 'size' => $size, 'quantity' => (int) $line['quantity'], 'unit_price' => $product->priceFor($size)];
            });

            $broughtOut = CounterItem::quantitiesOn(today());
            $soldToday = CounterItem::soldOn(today());
            $sizeBroughtOut = CounterItem::sizeQuantitiesOn(today());
            $soldBySize = CounterItem::soldBySizeOn(today());

            foreach ($lines->groupBy(fn (array $line) => $line['product']->id) as $productLines) {
                $product = $productLines->first()['product'];
                $sellable = CounterItem::sellableToday($product, $broughtOut, $soldToday);

                if ($productLines->sum('quantity') > $sellable) {
                    $where = ($broughtOut[$product->id] ?? null) === null ? 'in stock' : 'left at the counter';

                    throw ValidationException::withMessages(['items' => "Only {$sellable} of {$product->name} {$where}."]);
                }

                foreach ($productLines->filter(fn (array $line) => $line['size'] !== null)->groupBy(fn (array $line) => $line['size']->value) as $sizeLines) {
                    $size = $sizeLines->first()['size'];
                    $sizeSellable = CounterItem::sellableTodayInSize($product, $size, $sellable, $sizeBroughtOut, $soldBySize);

                    if ($sizeLines->sum('quantity') > $sizeSellable) {
                        throw ValidationException::withMessages(['items' => "Only {$sizeSellable} of {$product->name} ({$size->label()}) left at the counter."]);
                    }
                }
            }

            $subtotal = round($lines->sum(fn (array $line) => $line['unit_price'] * $line['quantity']), 2);
            $discount = round(min((float) ($validated['discount'] ?? 0), $subtotal), 2);
            $total = round($subtotal - $discount, 2);
            $paymentMethod = PaymentMethod::from($validated['payment_method']);

            if ($paymentMethod === PaymentMethod::Cash && (float) $validated['amount_tendered'] < $total) {
                throw ValidationException::withMessages(['amount_tendered' => 'The cash received is less than the total of RM '.number_format($total, 2).'.']);
            }

            $order = Order::create([
                'customer_name' => $validated['customer_name'] ?? null,
                'status' => OrderStatus::Completed,
                'channel' => SalesChannel::Counter,
                'delivery_method' => DeliveryMethod::Pickup,
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'amount_tendered' => $paymentMethod === PaymentMethod::Cash ? $validated['amount_tendered'] : null,
                'served_by' => $request->user()->id,
                'completed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'size' => $line['size'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => round($line['unit_price'] * $line['quantity'], 2),
                ]);

                $line['product']->adjustStock($line['size'], -$line['quantity']);
            }

            return $order;
        });

        $message = "Sale {$order->code} complete · RM ".number_format((float) $order->total, 2);

        if ($order->changeGiven() > 0) {
            $message .= ' · change RM '.number_format($order->changeGiven(), 2);
        }

        return redirect()->route('admin.pos.receipt', $order)->with('status', $message);
    }

    public function receipt(Order $order): View
    {
        abort_unless($order->isCounterSale(), 404);

        return view('admin.pos.receipt', ['order' => $order->load(['items', 'cashier'])]);
    }
}
