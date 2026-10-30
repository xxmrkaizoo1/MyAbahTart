<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function index(Request $request): View
    {
        $status = RefundStatus::tryFrom((string) $request->query('status'));

        return view('admin.refunds.index', [
            'status' => $status,
            'refunds' => Refund::query()
                ->with(['order.user', 'items.orderItem', 'processor'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [RefundStatus::Requested->value])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'pendingCount' => Refund::query()->pending()->count(),
            'refundedThisMonth' => (float) Refund::query()->approved()->where('processed_at', '>=', now()->startOfMonth())->sum('amount'),
            'refundCountThisMonth' => Refund::query()->approved()->where('processed_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    /**
     * Staff issue a refund straight away, e.g. after a phone call.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $order->load(['items', 'refunds.items']);

        if (! $order->canBeRefunded()) {
            return back()->with('error', "Order {$order->code} can't be refunded.");
        }

        $refundable = $order->refundableQuantities();

        $validated = $request->validateWithBag('refund', [
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'items' => ['nullable', 'array:'.implode(',', array_keys($refundable))],
            ...collect($refundable)->mapWithKeys(fn (int $max, int $itemId) => [
                "items.{$itemId}" => ['nullable', 'integer', 'min:0', "max:{$max}"],
            ])->all(),
            ...$this->payoutRules($order),
        ]);

        DB::transaction(function () use ($order, $request, $validated) {
            $refund = $order->refunds()->create([
                'requested_by' => $request->user()->id,
                'reason' => $validated['reason'],
                'details' => 'Issued by staff.',
            ]);

            foreach (array_filter(array_map('intval', $validated['items'] ?? [])) as $orderItemId => $quantity) {
                $refund->items()->create(['order_item_id' => $orderItemId, 'quantity' => $quantity]);
            }

            $refund->approve(
                $request->user(),
                (float) $validated['amount'],
                RefundMethod::from($validated['method']),
                $request->boolean('restock'),
                $validated['admin_note'] ?? null,
            );
        });

        return back()->with('status', 'Refunded RM '.number_format((float) $validated['amount'], 2)." on order {$order->code}.");
    }

    /**
     * Approve or decline a customer's refund request.
     */
    public function update(Request $request, Refund $refund): RedirectResponse
    {
        if ($refund->status !== RefundStatus::Requested) {
            return back()->with('error', 'This refund has already been handled.');
        }

        $order = $refund->order->load(['items', 'refunds.items']);

        if ($request->input('decision') === 'reject') {
            $validated = $request->validateWithBag('refund', [
                'admin_note' => ['required', 'string', 'max:1000'],
            ], ['admin_note.required' => 'Tell the customer why the refund was declined.']);

            $refund->reject($request->user(), $validated['admin_note']);

            return back()->with('status', "Refund for order {$order->code} declined.");
        }

        $validated = $request->validateWithBag('refund', $this->payoutRules($order));

        $refund->approve(
            $request->user(),
            (float) $validated['amount'],
            RefundMethod::from($validated['method']),
            $request->boolean('restock'),
            $validated['admin_note'] ?? null,
        );

        return back()->with('status', 'Refunded RM '.number_format((float) $validated['amount'], 2)." on order {$order->code}.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function payoutRules(Order $order): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$order->refundableAmount()],
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'restock' => ['boolean'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
