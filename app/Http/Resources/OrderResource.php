<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $next = $this->status->next();
        $hasRefundDetails = $this->relationLoaded('refunds') && $this->relationLoaded('items');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->statusLabel(),
            'status_short_label' => $this->status->shortLabel(),
            'is_active' => $this->status->isActive(),
            'next_status' => $next === null ? null : [
                'value' => $next->value,
                'action_label' => $next === OrderStatus::Ready ? 'Mark '.strtolower($this->delivery_method->readyLabel()) : $next->actionLabel(),
            ],
            'timeline' => collect(OrderStatus::timeline())->map(fn (OrderStatus $status) => [
                'status' => $status->value,
                'label' => $status === OrderStatus::Ready ? $this->delivery_method->readyLabel() : $status->label(),
                'at' => $this->timestampFor($status),
            ]),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'delivery_method' => $this->delivery_method?->value,
            'delivery_label' => $this->delivery_method?->label(),
            'requires_address' => $this->delivery_method?->requiresAddress() ?? false,
            'customer_name' => $this->customerName(),
            'customer_initials' => $this->customerInitials(),
            'customer_email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'voucher_code' => $this->voucher_code,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'payment_status' => $this->payment_status?->value,
            'payment_label' => $this->paymentLabel(),
            'is_awaiting_payment' => $this->isAwaitingPayment(),
            'amount_tendered' => $this->amount_tendered === null ? null : (float) $this->amount_tendered,
            'change_given' => $this->changeGiven(),
            'cashier' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            'item_count' => $this->whenLoaded('items', fn () => $this->itemCount()),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'cancellation_reason' => $this->cancellation_reason?->value,
            'cancellation_reason_label' => $this->cancellation_reason?->label(),
            'cancellation_note' => $this->cancellation_note,
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'refunded_amount' => $this->whenLoaded('refunds', fn () => $this->refundedAmount()),
            'refundable_amount' => $this->whenLoaded('refunds', fn () => $this->refundableAmount()),
            'can_be_refunded' => $this->when($hasRefundDetails, fn () => $this->canBeRefunded()),
            'refundable_quantities' => $this->when($hasRefundDetails, fn () => (object) $this->refundableQuantities()),
            'created_at' => $this->created_at,
            'preparing_at' => $this->preparing_at,
            'ready_at' => $this->ready_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
