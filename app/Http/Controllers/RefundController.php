<?php

namespace App\Http\Controllers;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    /**
     * A customer asks for money back on items from a completed order.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['items', 'refunds.items']);

        if (! $order->canRequestRefund()) {
            return back()->with('error', 'This order can no longer be refunded online. Please contact us instead.');
        }

        $refundable = $order->refundableQuantities();

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'details' => ['nullable', 'required_if:reason,'.RefundReason::Other->value, 'string', 'max:1000'],
            'items' => ['required', 'array:'.implode(',', array_keys($refundable))],
            ...collect($refundable)->mapWithKeys(fn (int $max, int $itemId) => [
                "items.{$itemId}" => ['nullable', 'integer', 'min:0', "max:{$max}"],
            ])->all(),
        ], [
            'details.required_if' => 'Please tell us what went wrong.',
        ]);

        $quantities = array_filter(array_map('intval', $validated['items']));

        if ($quantities === []) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item to refund.']);
        }

        DB::transaction(function () use ($order, $request, $validated, $quantities) {
            $refund = $order->refunds()->create([
                'requested_by' => $request->user()->id,
                'status' => RefundStatus::Requested,
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]);

            foreach ($quantities as $orderItemId => $quantity) {
                $refund->items()->create(['order_item_id' => $orderItemId, 'quantity' => $quantity]);
            }
        });

        return back()->with('status', 'Refund request sent. We’ll review it within one working day.');
    }
}
