<?php

namespace App\Models;

use App\Enums\ProductSize;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id', 'name', 'slug', 'tagline', 'description', 'price', 'size_prices', 'unit_label', 'weight_grams', 'art',
    'image_path', 'size_images', 'stock', 'size_stock', 'size_pieces', 'size_grams', 'low_stock_threshold', 'is_active', 'is_bestseller', 'is_seasonal',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The illustration styles available for products without a photo.
     *
     * @var array<string, string>
     */
    public const ARTS = [
        'pineapple' => 'Pineapple',
        'egg' => 'Egg custard',
        'cheese' => 'Cheese',
        'berry' => 'Mixed berries',
        'mango' => 'Mango',
        'matcha' => 'Matcha',
        'nutty' => 'Nuts',
        'chocolate' => 'Chocolate',
        'savory' => 'Savoury pie',
        'durian' => 'Durian',
        'lavender' => 'Earl Grey / Lavender',
        'lemon' => 'Lemon meringue',
        'caramel' => 'Salted caramel',
        'pistachio' => 'Pistachio',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'size_prices' => 'array',
            'weight_grams' => 'integer',
            'stock' => 'integer',
            'size_stock' => 'array',
            'size_pieces' => 'array',
            'size_grams' => 'array',
            'size_images' => 'array',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_seasonal' => 'boolean',
        ];
    }

    /**
     * Products sold in sizes keep a count per size, and their total stock is always the sum of those counts.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->hasSizes()) {
                $product->size_stock = $product->sizeStock();
                $product->stock = array_sum($product->size_stock);
                $product->size_pieces = $product->sizePieces() ?: null;
                $product->size_grams = $product->sizeGrams() ?: null;
            } else {
                $product->size_stock = null;
                $product->size_pieces = null;
                $product->size_grams = null;
            }
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Only products customers can see and buy.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The photo for a size when it has its own, otherwise the main product photo.
     */
    public function imageUrl(?ProductSize $size = null): ?string
    {
        return $this->sizeImageUrl($size) ?? ($this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }

    /**
     * The picture shown for products that have no photo yet.
     */
    public static function defaultImageUrl(): string
    {
        return asset('images/default-product.svg');
    }

    /**
     * The photo uploaded for one size only, if any.
     */
    public function sizeImageUrl(?ProductSize $size): ?string
    {
        $path = $size === null ? null : ($this->size_images[$size->value] ?? null);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Every stored photo path, main and per size.
     *
     * @return list<string>
     */
    public function imagePaths(): array
    {
        return array_values(array_filter([$this->image_path, ...array_values($this->size_images ?? [])]));
    }

    /**
     * How the product is sold, with its weight when set, e.g. "Box of 12 · 300 g".
     */
    public function unitLabel(): string
    {
        return $this->unit_label.($this->weight_grams ? " · {$this->weight_grams} g" : '');
    }

    /**
     * The sizes this product is sold in, smallest first, with their prices.
     *
     * @return array<string, float> Prices keyed by size code (S, M, L).
     */
    public function sizePrices(): array
    {
        $prices = $this->size_prices ?? [];

        return collect(ProductSize::cases())
            ->filter(fn (ProductSize $size) => isset($prices[$size->value]))
            ->mapWithKeys(fn (ProductSize $size) => [$size->value => (float) $prices[$size->value]])
            ->all();
    }

    public function hasSizes(): bool
    {
        return $this->sizePrices() !== [];
    }

    public function offersSize(?ProductSize $size): bool
    {
        return $size === null ? ! $this->hasSizes() : array_key_exists($size->value, $this->sizePrices());
    }

    /**
     * The price of one unit in the given size, or the base price for products without sizes.
     */
    public function priceFor(?ProductSize $size): float
    {
        return $size === null ? (float) $this->price : ($this->sizePrices()[$size->value] ?? (float) $this->price);
    }

    /**
     * How many of each offered size are in stock.
     *
     * @return array<string, int> Counts keyed by size code (S, M, L).
     */
    public function sizeStock(): array
    {
        $stock = $this->size_stock ?? [];

        return collect($this->sizePrices())
            ->map(fn (float $price, string $code) => max(0, (int) ($stock[$code] ?? 0)))
            ->all();
    }

    /**
     * How many pieces come in each offered size, for the sizes the admin filled in.
     *
     * @return array<string, int> Piece counts keyed by size code (S, M, L).
     */
    public function sizePieces(): array
    {
        return $this->filledSizeAmounts($this->size_pieces);
    }

    /**
     * How many grams each offered size weighs, for the sizes the admin filled in.
     *
     * @return array<string, int> Weights keyed by size code (S, M, L).
     */
    public function sizeGrams(): array
    {
        return $this->filledSizeAmounts($this->size_grams);
    }

    /**
     * The optional details of a size, e.g. "12 pcs · 250 g", or an empty string when none are set.
     */
    public function sizeDetails(ProductSize $size): string
    {
        $pieces = $this->sizePieces()[$size->value] ?? null;
        $grams = $this->sizeGrams()[$size->value] ?? null;

        return implode(' · ', array_filter([
            $pieces ? "{$pieces} pcs" : null,
            $grams ? "{$grams} g" : null,
        ]));
    }

    /**
     * The size's label with its details when known, e.g. "Medium · 12 pcs · 250 g".
     */
    public function sizeLabel(ProductSize $size): string
    {
        $details = $this->sizeDetails($size);

        return $size->label().($details !== '' ? " · {$details}" : '');
    }

    /**
     * Positive whole amounts for the offered sizes only.
     *
     * @param  array<string, mixed>|null  $amounts
     * @return array<string, int>
     */
    private function filledSizeAmounts(?array $amounts): array
    {
        return collect($this->sizePrices())
            ->map(fn (float $price, string $code) => (int) ($amounts[$code] ?? 0))
            ->filter(fn (int $amount) => $amount > 0)
            ->all();
    }

    /**
     * How many can be sold in the given size, or in total for products without sizes.
     */
    public function stockFor(?ProductSize $size): int
    {
        if ($size === null || ! $this->hasSizes()) {
            return $this->stock;
        }

        return $this->sizeStock()[$size->value] ?? 0;
    }

    /**
     * Add to or take from the stock of one size (or of the product when it has no sizes).
     */
    public function adjustStock(?ProductSize $size, int $change): void
    {
        if (! $this->hasSizes()) {
            $this->forceFill(['stock' => max(0, $this->stock + $change)])->save();

            return;
        }

        $code = $size !== null && $this->offersSize($size) ? $size->value : $this->defaultSize()->value;
        $sizeStock = $this->sizeStock();
        $sizeStock[$code] = max(0, $sizeStock[$code] + $change);

        $this->forceFill(['size_stock' => $sizeStock])->save();
    }

    /**
     * The first size that is in stock, or the smallest size when every size is sold out.
     */
    public function defaultSize(): ?ProductSize
    {
        $sizeStock = $this->sizeStock();
        $code = collect($sizeStock)->filter(fn (int $count) => $count > 0)->keys()->first() ?? array_key_first($sizeStock);

        return $code === null ? null : ProductSize::from($code);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }

    public function isSoldOut(): bool
    {
        return $this->stock < 1;
    }

    /**
     * How full the stock is relative to a healthy level, from 0 to 100.
     */
    public function stockPercentage(): int
    {
        $healthyLevel = max($this->low_stock_threshold * 4, 1);

        return (int) min(100, round($this->stock / $healthyLevel * 100));
    }
}
