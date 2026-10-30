<?php

namespace App\Models;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'order_id', 'requested_by', 'processed_by', 'status', 'reason', 'details', 'amount',
    'method', 'restocked', 'admin_note', 'processed_at',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * How many days after an order is completed the customer may ask for a refund.
     */
    public const REQUEST_WINDOW_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'reason' => RefundReason::class,
            'method' => RefundMethod::class,
            'amount' => 'decimal:2',
            'restocked' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return HasMany<RefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    /**
     * @param  Builder<Refund>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', RefundStatus::Requested);
    }

    /**
     * @param  Builder<Refund>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', RefundStatus::Approved);
    }

    /**
     * The value of the refunded items at the prices the customer paid.
     */
    public function itemsValue(): float
    {
        return round((float) $this->items->sum(fn (RefundItem $item) => $item->value()), 2);
    }

    /**
     * Pay the refund out and, if asked, put the returned items back into stock.
     */
    public function approve(User $admin, float $amount, RefundMethod $method, bool $restock, ?string $note = null): void
    {
        DB::transaction(function () use ($admin, $amount, $method, $restock, $note) {
            $this->update([
                'status' => RefundStatus::Approved,
                'amount' => round($amount, 2),
                'method' => $method,
                'restocked' => $restock,
                'admin_note' => $note,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            if ($restock) {
                $this->items->loadMissing('orderItem.product')->each(function (RefundItem $item) {
                    $item->orderItem->product?->adjustStock($item->orderItem->size, $item->quantity);
                });
            }
        });
    }

    public function reject(User $admin, ?string $note = null): void
    {
        $this->update([
            'status' => RefundStatus::Rejected,
            'amount' => 0,
            'admin_note' => $note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }
}
