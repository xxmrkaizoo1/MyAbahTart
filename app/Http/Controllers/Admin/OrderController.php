<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search', ''));

        $orders = Order::query()
            ->with(['user', 'items'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $counts = Order::query()
            ->where(function ($query) {
                $query->active()->orWhereDate('created_at', today());
            })
            ->toBase()
            ->selectRaw('status, COUNT(*) as order_count')
            ->groupBy('status')
            ->pluck('order_count', 'status')
            ->map(fn ($count) => (int) $count);

        return view('admin.orders.index', [
            'orders' => $orders,
            'activeStatus' => $status,
            'search' => $search,
            'counts' => $counts,
            'completedToday' => Order::query()->where('status', OrderStatus::Completed)->whereDate('completed_at', today())->count(),
            'board' => Order::query()->active()->with(['user', 'items'])->oldest()->get()->groupBy(fn (Order $order) => $order->status->value),
            'kitchenFocus' => $insights->kitchenFocus(),
        ]);
    }

    public function show(Order $order, ShopInsights $insights): View
    {
        $order->load(['user', 'items.product', 'refunds.items.orderItem', 'refunds.processor']);

        return view('admin.orders.show', [
            'order' => $order,
            'estimate' => $insights->estimatedReadyAt($order),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', Rule::enum(CancellationReason::class)],
            'cancellation_note' => ['required_if:cancellation_reason,other', 'nullable', 'string', 'max:500'],
        ], [
            'cancellation_reason.required_if' => 'Choose why the order is being cancelled.',
            'cancellation_note.required_if' => 'Tell the customer what went wrong.',
        ]);

        $status = OrderStatus::from($validated['status']);

        if (! $order->status->isActive()) {
            return back()->with('error', 'This order is already closed.');
        }

        if ($status !== OrderStatus::Cancelled && $status !== $order->status->next()) {
            return back()->with('error', 'Orders must move through each status in turn.');
        }

        DB::transaction(function () use ($order, $status, $validated) {
            if ($status === OrderStatus::Cancelled) {
                foreach ($order->items()->with('product')->get() as $item) {
                    $item->product?->adjustStock($item->size, $item->quantity);
                }

                $order->fill([
                    'cancellation_reason' => $validated['cancellation_reason'],
                    'cancellation_note' => $validated['cancellation_note'] ?? null,
                ]);
            }

            $order->transitionTo($status);
        });

        return back()->with('status', "Order {$order->code} updated: {$order->statusLabel()}.");
    }
}
