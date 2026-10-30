<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\ShopInsights;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, ShopInsights $insights): View
    {
        $user = $request->user();

        return view('shop.home', [
            'featured' => Product::query()->available()->with('category')->where('is_seasonal', true)->latest()->first(),
            'heroProducts' => Product::query()->available()->where('is_bestseller', true)->take(3)->get(),
            'categories' => Category::query()
                ->withCount(['products' => fn ($query) => $query->available()])
                ->orderBy('sort_order')
                ->get(),
            'recommendations' => $insights->recommendationsFor($user, 4),
            'newArrivals' => Product::query()->available()->with('category')->withAvg('reviews', 'rating')->latest()->take(4)->get(),
            'bestsellers' => $insights->popularProducts(4, 30),
            'reviews' => Review::query()->with(['user', 'product'])->where('rating', 5)->latest()->take(3)->get(),
            'stats' => [
                'flavours' => Product::query()->available()->count(),
                'rating' => round((float) Review::query()->avg('rating'), 1),
                'orders' => Order::query()->notCancelled()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'marquee' => Product::query()->available()->orderBy('name')->pluck('name'),
            'activeOrder' => $user ? Order::query()->whereBelongsTo($user)->active()->latest()->first() : null,
        ]);
    }
}
