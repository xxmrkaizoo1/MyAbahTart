<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ProductSize;
use App\Enums\SalesChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A product set out for sale at the counter on a particular day,
 * optionally with how many units were brought out (in total, or per size for sized products).
 */
#[Fillable(['sale_date', 'product_id', 'quantity', 'size_quantities'])]
class CounterItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'quantity' => 'integer',
            'size_quantities' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<CounterItem>  $query
     */
    #[Scope]
    protected function forDate(Builder $query, Carbon $date): void
    {
        $query->whereDate('sale_date', $date);
    }

    /**
     * Product ids on the counter for the given day.
     *
     * @return array<int, int>
     */
    public static function productIdsOn(Carbon $date): array
    {
        return static::query()->forDate($date)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Units brought to the counter for the given day, keyed by product id (null means no limit).
     *
     * @return Collection<int, int|null>
     */
    public static function quantitiesOn(Carbon $date): Collection
    {
        return static::query()
            ->forDate($date)
            ->pluck('quantity', 'product_id')
            ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => $quantity === null ? null : (int) $quantity]);
    }

    /**
     * Units of each size brought to the counter for the given day, keyed by product id then size code (null means no limit).
     *
     * @return Collection<int, array<string, int|null>>
     */
    public static function sizeQuantitiesOn(Carbon $date): Collection
    {
        return static::query()
            ->forDate($date)
            ->whereNotNull('size_quantities')
            ->get(['product_id', 'size_quantities'])
            ->mapWithKeys(fn (CounterItem $item) => [$item->product_id => $item->size_quantities]);
    }

    /**
     * Units of each size sold at the counter on the given day, keyed by product id then size code.
     *
     * @return Collection<int, array<string, int>>
     */
    public static function soldBySizeOn(Carbon $date): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.channel', SalesChannel::Counter->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('orders.created_at', $date)
            ->whereNotNull('order_items.product_id')
            ->whereNotNull('order_items.size')
            ->groupBy('order_items.product_id', 'order_items.size')
            ->selectRaw('order_items.product_id, order_items.size, SUM(order_items.quantity) as units')
            ->toBase()
            ->get()
            ->groupBy('product_id')
            ->mapWithKeys(fn (Collection $rows, $productId) => [(int) $productId => $rows->mapWithKeys(fn (object $row) => [$row->size => (int) $row->units])->all()]);
    }

    /**
     * Units sold at the counter on the given day, keyed by product id.
     *
     * @return Collection<int, int>
     */
    public static function soldOn(Carbon $date): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.channel', SalesChannel::Counter->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('orders.created_at', $date)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units')
            ->toBase()
            ->pluck('units', 'product_id')
            ->mapWithKeys(fn ($units, $productId) => [(int) $productId => (int) $units]);
    }

    /**
     * How many of a product the counter can still sell today: whatever is left of the
     * amount brought out, but never more than the shop's actual stock.
     */
    public static function sellableToday(Product $product, ?Collection $quantities = null, ?Collection $sold = null): int
    {
        $quantities ??= static::quantitiesOn(today());
        $brought = $quantities[$product->id] ?? null;

        if ($brought === null) {
            return max(0, $product->stock);
        }

        $sold ??= static::soldOn(today());

        return max(0, min($product->stock, $brought - ($sold[$product->id] ?? 0)));
    }

    /**
     * How many of one size the counter can still sell today: what is left of that size's
     * allowance, never more than the size's stock or what the product as a whole has left.
     *
     * @param  Collection<int, array<string, int|null>>|null  $sizeQuantities
     * @param  Collection<int, array<string, int>>|null  $soldBySize
     */
    public static function sellableTodayInSize(Product $product, ProductSize $size, int $sellableProduct, ?Collection $sizeQuantities = null, ?Collection $soldBySize = null): int
    {
        $sizeQuantities ??= static::sizeQuantitiesOn(today());
        $brought = $sizeQuantities[$product->id][$size->value] ?? null;
        $available = min($product->stockFor($size), $sellableProduct);

        if ($brought === null) {
            return max(0, $available);
        }

        $soldBySize ??= static::soldBySizeOn(today());

        return max(0, min($available, $brought - ($soldBySize[$product->id][$size->value] ?? 0)));
    }
}
