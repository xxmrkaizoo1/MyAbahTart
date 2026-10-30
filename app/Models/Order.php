<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SalesChannel;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'user_id', 'customer_name', 'status', 'channel', 'delivery_method', 'phone', 'address',
    'notes', 'subtotal', 'delivery_fee', 'discount', 'total', 'payment_method', 'amount_tendered',
    'served_by', 'voucher_code', 'preparing_at', 'ready_at', 'completed_at', 'cancelled_at',
    'cancellation_reason', 'cancellation_note', 'payment_status', 'billplz_bill_id', 'payment_url', 'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'channel' => SalesChannel::class,
            'payment_method' => PaymentMethod::class,
            'amount_tendered' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'delivery_method' => DeliveryMethod::class,
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_reason' => CancellationReason::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= static::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'TL-'.Str::upper(Str::random(5));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member who rang up a counter sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders that still need work from the kitchen.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Received, OrderStatus::Preparing, OrderStatus::Ready]);
    }

    /**
     * Orders that count towards revenue.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function notCancelled(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled);
    }

    /**
     * Walk-in sales rung up at the counter.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function atCounter(Builder $query): void
    {
        $query->where('channel', SalesChannel::Counter);
    }

    public function isCounterSale(): bool
    {
        return $this->channel === SalesChannel::Counter;
    }

    public function customerName(): string
    {
        return $this->user?->name ?? $this->customer_name ?? 'Walk-in customer';
    }

    public function customerInitials(): string
    {
        return $this->user?->initials()
            ?? collect(explode(' ', $this->customer_name ?? 'Walk in'))->take(2)->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    }

    /**
     * Change handed back for a cash payment.
     */
    public function changeGiven(): ?float
    {
        return $this->amount_tendered === null ? null : round((float) $this->amount_tendered - (float) $this->total, 2);
    }

    /**
     * Whether the customer still has to pay online before the shop starts on the order.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Online
            && $this->payment_status === PaymentStatus::Unpaid
            && $this->status !== OrderStatus::Cancelled;
    }

    /**
     * Whether the customer pays in cash when the order is delivered or collected.
     */
    public function isCashOnDelivery(): bool
    {
        return ! $this->isCounterSale() && $this->payment_method === PaymentMethod::Cash;
    }

    /**
     * How the order is being paid, in words for badges.
     */
    public function paymentLabel(): ?string
    {
        if ($this->payment_status === PaymentStatus::Paid) {
            return $this->isCashOnDelivery() ? 'Paid in cash' : 'Paid online';
        }

        if ($this->isCashOnDelivery()) {
            return $this->delivery_method?->requiresAddress() === false ? 'Pay at pickup' : 'Cash on delivery';
        }

        return $this->payment_status?->label();
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function statusLabel(): string
    {
        return $this->status === OrderStatus::Ready
            ? $this->delivery_method->readyLabel()
            : $this->status->label();
    }

    public function timestampFor(OrderStatus $status): ?Carbon
    {
        return match ($status) {
            OrderStatus::Received => $this->created_at,
            OrderStatus::Preparing => $this->preparing_at,
            OrderStatus::Ready => $this->ready_at,
            OrderStatus::Completed => $this->completed_at,
            OrderStatus::Cancelled => $this->cancelled_at,
        };
    }

    /**
     * Move the order to the given status and stamp the time it happened.
     */
    public function transitionTo(OrderStatus $status): void
    {
        $this->status = $status;

        $column = match ($status) {
            OrderStatus::Preparing => 'preparing_at',
            OrderStatus::Ready => 'ready_at',
            OrderStatus::Completed => 'completed_at',
            OrderStatus::Cancelled => 'cancelled_at',
            default => null,
        };

        if ($column !== null) {
            $this->{$column} = now();
        }

        if ($status === OrderStatus::Completed && $this->isCashOnDelivery() && $this->payment_status === PaymentStatus::Unpaid) {
            $this->payment_status = PaymentStatus::Paid;
            $this->paid_at = now();
        }

        $this->save();
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Money already paid back to the customer.
     */
    public function refundedAmount(): float
    {
        return round((float) $this->refunds->where('status', RefundStatus::Approved)->sum('amount'), 2);
    }

    /**
     * The most that can still be refunded on this order.
     */
    public function refundableAmount(): float
    {
        return round(max(0, (float) $this->total - $this->refundedAmount()), 2);
    }

    public function pendingRefund(): ?Refund
    {
        return $this->refunds->firstWhere('status', RefundStatus::Requested);
    }

    /**
     * Units of each item not yet refunded or awaiting a refund decision.
     *
     * @return array<int, int> Quantities keyed by order item id.
     */
    public function refundableQuantities(): array
    {
        $claimed = $this->refunds
            ->where('status', '!=', RefundStatus::Rejected)
            ->flatMap(fn (Refund $refund) => $refund->items)
            ->groupBy('order_item_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        return $this->items
            ->mapWithKeys(fn (OrderItem $item) => [$item->id => max(0, $item->quantity - ($claimed[$item->id] ?? 0))])
            ->all();
    }

    /**
     * Whether staff can still pay money back on this order.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === OrderStatus::Completed && $this->refundableAmount() > 0;
    }

    /**
     * Whether the customer can still ask for a refund themselves.
     */
    public function canRequestRefund(): bool
    {
        return $this->canBeRefunded()
            && $this->user_id !== null
            && $this->pendingRefund() === null
            && $this->completed_at?->gt(now()->subDays(Refund::REQUEST_WINDOW_DAYS)) === true
            && array_sum($this->refundableQuantities()) > 0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->refundedAmount() > 0 && $this->refundableAmount() === 0.0;
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
