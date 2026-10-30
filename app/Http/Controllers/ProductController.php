<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShopInsights;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * @var array<string, string>
     */
    public const SORTS = [
        'popular' => 'Most popular',
        'newest' => 'Newest',
        'price-low' => 'Price: low to high',
        'price-high' => 'Price: high to low',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $category = Category::query()->where('slug', $request->query('category'))->first();
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'popular';

        $products = Product::query()
            ->available()
            ->with('category')
            ->withAvg('reviews', 'rating')
            ->withSum('orderItems as units_sold', 'quantity')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('tagline', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($category, fn ($query) => $query->whereBelongsTo($category))
            ->when($sort === 'price-low', fn ($query) => $query->orderBy('price'))
            ->when($sort === 'price-high', fn ($query) => $query->orderByDesc('price'))
            ->when($sort === 'newest', fn ($query) => $query->latest())
            ->when($sort === 'popular', fn ($query) => $query->orderByDesc('units_sold'))
            ->get();

        return view('shop.products.index', [
            'products' => $products,
            'categories' => Category::query()->withCount(['products' => fn ($query) => $query->available()])->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'search' => $search,
            'sort' => $sort,
            'sorts' => self::SORTS,
        ]);
    }

    public function show(Request $request, Product $product, ShopInsights $insights): View
    {
        abort_unless($product->is_active, 404);

        $product->load('category')->loadAvg('reviews', 'rating')->loadCount('reviews');

        $user = $request->user();

        return view('shop.products.show', [
            'product' => $product,
            'reviews' => $product->reviews()->with('user')->latest()->take(9)->get(),
            'ratingBreakdown' => $product->reviews()->selectRaw('rating, COUNT(*) as review_count')->groupBy('rating')->pluck('review_count', 'rating'),
            'boughtTogether' => $insights->boughtTogether($product),
            'related' => Product::query()->available()
                ->with('category')
                ->withAvg('reviews', 'rating')
                ->whereBelongsTo($product->category)
                ->whereKeyNot($product->id)
                ->take(4)
                ->get(),
            'canReview' => $user !== null
                && $user->orders()->whereHas('items', fn ($query) => $query->where('product_id', $product->id))->exists()
                && ! $product->reviews()->whereBelongsTo($user)->exists(),
        ]);
    }
}
