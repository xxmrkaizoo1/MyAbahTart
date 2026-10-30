<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\ProductSize;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * A shopping cart kept in the visitor's session.
 */
class Cart
{
    private const ITEMS_KEY = 'cart.items';

    private const VOUCHER_KEY = 'cart.voucher';

    /**
     * @var Collection<int, array{key: string, product: Product, size: ProductSize|null, quantity: int, unit_price: float, line_total: float}>|null
     */
    private ?Collection $resolvedLines = null;

    public function __construct(private Session $session) {}

    /**
     * @return array<string, int> Quantities keyed by line key ("12" or "12:M" for a sized product).
     */
    public function items(): array
    {
        return $this->session->get(self::ITEMS_KEY, []);
    }

    public static function lineKey(Product $product, ?ProductSize $size = null): string
    {
        return $size === null ? (string) $product->id : "{$product->id}:{$size->value}";
    }

    /**
     * Add a product, capping the quantity at the available stock. Returns the new quantity.
     */
    public function add(Product $product, int $quantity = 1, ?ProductSize $size = null): int
    {
        $items = $this->items();

        return $this->put($product, ($items[self::lineKey($product, $size)] ?? 0) + $quantity, $size);
    }

    /**
     * Set a product's quantity in one size, capped at the stock of that size. Returns the new quantity.
     */
    public function put(Product $product, int $quantity, ?ProductSize $size = null): int
    {
        $items = $this->items();
        $key = self::lineKey($product, $size);
        $quantity = max(0, min($quantity, $product->stockFor($size)));

        if ($quantity === 0) {
            unset($items[$key]);
        } else {
            $items[$key] = $quantity;
        }

        $this->session->put(self::ITEMS_KEY, $items);
        $this->resolvedLines = null;

        return $quantity;
    }

    public function remove(Product $product, ?ProductSize $size = null): void
    {
        $this->put($product, 0, $size);
    }

    public function clear(): void
    {
        $this->session->forget([self::ITEMS_KEY, self::VOUCHER_KEY]);
        $this->resolvedLines = null;
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * The cart contents with fresh product data and prices.
     * Products that are gone or hidden, and sizes no longer offered, are dropped.
     *
     * @return Collection<int, array{key: string, product: Product, size: ProductSize|null, quantity: int, unit_price: float, line_total: float}>
     */
    public function lines(): Collection
    {
        if ($this->resolvedLines !== null) {
            return $this->resolvedLines;
        }

        $items = $this->items();

        $products = Product::query()
            ->available()
            ->with('category')
            ->whereKey(collect(array_keys($items))->map(fn (string|int $key) => $this->productIdFrom($key))->unique()->all())
            ->get()
            ->keyBy('id');

        return $this->resolvedLines = collect($items)
            ->map(function (int $quantity, string|int $key) use ($products): ?array {
                $product = $products[$this->productIdFrom($key)] ?? null;
                $size = str_contains((string) $key, ':') ? ProductSize::tryFrom(explode(':', (string) $key)[1]) : null;

                if ($product === null || ! $product->offersSize($size)) {
                    return null;
                }

                $unitPrice = $product->priceFor($size);

                return [
                    'key' => (string) $key,
                    'product' => $product,
                    'size' => $size,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Total units of a product in the cart across all its sizes.
     */
    public function quantityOf(Product $product): int
    {
        return $this->lines()->where('product.id', $product->id)->sum('quantity');
    }

    public function subtotal(): float
    {
        return round($this->lines()->sum('line_total'), 2);
    }

    public function applyVoucher(Voucher $voucher): void
    {
        $this->session->put(self::VOUCHER_KEY, $voucher->code);
    }

    public function forgetVoucher(): void
    {
        $this->session->forget(self::VOUCHER_KEY);
    }

    public function voucher(): ?Voucher
    {
        $code = $this->session->get(self::VOUCHER_KEY);

        return $code ? Voucher::findByCode($code) : null;
    }

    /**
     * Price breakdown for the given delivery method, or for a fee quoted live (e.g. by Lalamove).
     *
     * @return array{subtotal: float, delivery_fee: float, discount: float, total: float, voucher: ?Voucher, free_delivery_gap: float}
     */
    public function summary(DeliveryMethod $deliveryMethod, ?float $quotedFee = null): array
    {
        $subtotal = $this->subtotal();
        $voucher = $this->voucher();
        $discount = $voucher?->discountFor($subtotal) ?? 0.0;
        $deliveryFee = $quotedFee ?? $deliveryMethod->feeFor($subtotal);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'total' => round(max(0, $subtotal - $discount + $deliveryFee), 2),
            'voucher' => $voucher,
            'free_delivery_gap' => DeliveryMethod::freeDeliveryThreshold() === null ? 0.0 : round(max(0, DeliveryMethod::freeDeliveryThreshold() - $subtotal), 2),
        ];
    }

    private function productIdFrom(string|int $lineKey): int
    {
        return (int) explode(':', (string) $lineKey)[0];
    }
}
