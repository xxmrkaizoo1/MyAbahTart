<?php

namespace App\Http\Resources;

use App\Models\Refund;
use App\Models\RefundItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasItemDetails = $this->relationLoaded('items')
            && $this->items->every(fn (RefundItem $item) => $item->relationLoaded('orderItem') && $item->orderItem !== null);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'details' => $this->details,
            'amount' => (float) $this->amount,
            'method' => $this->method?->value,
            'method_label' => $this->method?->label(),
            'restocked' => $this->restocked,
            'admin_note' => $this->admin_note,
            'processor' => $this->whenLoaded('processor', fn () => $this->processor?->name),
            'processed_at' => $this->processed_at,
            'items' => $this->when($hasItemDetails, fn () => $this->items->map(fn (RefundItem $item) => [
                'order_item_id' => $item->order_item_id,
                'name' => $item->orderItem->displayName(),
                'quantity' => $item->quantity,
                'value' => $item->value(),
            ])->values()),
            'items_value' => $this->when($hasItemDetails, fn () => $this->itemsValue()),
            'order' => $this->whenLoaded('order', fn () => [
                'code' => $this->order->code,
                'customer_name' => $this->order->customerName(),
                'total' => (float) $this->order->total,
                'refundable_amount' => $this->order->relationLoaded('refunds') ? $this->order->refundableAmount() : null,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
