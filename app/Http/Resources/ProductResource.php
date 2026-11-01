<?php

namespace App\Http\Resources;

use App\Enums\ProductSize;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sizeStock = $this->sizeStock();
        $sizePieces = $this->sizePieces();
        $sizeGrams = $this->sizeGrams();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'price' => (float) $this->price,
            'sizes' => collect($this->sizePrices())->map(fn (float $price, string $code) => [
                'code' => $code,
                'label' => ProductSize::from($code)->label(),
                'full_label' => $this->sizeLabel(ProductSize::from($code)),
                'price' => $price,
                'stock' => $sizeStock[$code] ?? 0,
                'pieces' => $sizePieces[$code] ?? null,
                'grams' => $sizeGrams[$code] ?? null,
                'image_url' => $this->sizeImageUrl(ProductSize::from($code)),
            ])->values(),
            'unit_label' => $this->unit_label,
            'unit_label_full' => $this->unitLabel(),
            'weight_grams' => $this->weight_grams,
            'art' => $this->art,
            'image_url' => $this->imageUrl() ?? Product::defaultImageUrl(),
            'has_photo' => $this->image_path !== null,
            'stock' => $this->stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'stock_percentage' => $this->stockPercentage(),
            'is_low_stock' => $this->isLowStock(),
            'is_sold_out' => $this->isSoldOut(),
            'is_active' => $this->is_active,
            'is_bestseller' => $this->is_bestseller,
            'is_seasonal' => $this->is_seasonal,
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'author' => $review->user?->name ?? 'A customer',
                'created_at' => $review->created_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
