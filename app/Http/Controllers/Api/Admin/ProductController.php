<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = app()->call([app(AdminProductController::class), 'index'], ['request' => $request])->getData();

        return response()->json([
            'products' => ProductResource::collection($data['products']),
            'categories' => CategoryResource::collection($data['categories']),
            'active_category' => $data['activeCategory']?->slug,
            'search' => $data['search'],
            'stock_level' => $data['stockLevel'],
            'total_products' => $data['totalProducts'],
            'new_this_month' => $data['newThisMonth'],
            'low_stock_count' => $data['lowStockCount'],
            'sold_out_count' => $data['soldOutCount'],
            'running_out' => $data['runningOut'] === null ? null : [
                'product' => new ProductResource($data['runningOut']['product']),
                'days_left' => round($data['runningOut']['days_left'], 1),
            ],
            'units_sold' => (object) $data['unitsSold']->all(),
        ]);
    }

    /**
     * One product for the edit form, with its reviews so admins can remove unsuitable ones.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'reviews' => fn ($query) => $query->with('user')->latest()]);

        return response()->json(['product' => new ProductResource($product)]);
    }
}
