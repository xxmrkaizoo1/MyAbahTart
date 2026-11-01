<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProductSize;
use App\Http\Controllers\Admin\CounterMenuController;
use App\Http\Controllers\Admin\PosController as AdminPosController;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\CounterItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The point-of-sale screen for walk-in customers.
 */
class PosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = app(AdminPosController::class)->index($request)->getData();

        return response()->json([
            'products' => $data['products']->map(function (Product $product) use ($data) {
                $sellable = CounterItem::sellableToday($product, $data['broughtOut'], $data['soldToday']);

                return [
                    'product' => new ProductResource($product),
                    'brought_out' => $data['broughtOut'][$product->id] ?? null,
                    'sold_today' => $data['soldToday'][$product->id] ?? 0,
                    'sellable' => $sellable,
                    'size_sellable' => (object) collect($product->sizePrices())->map(
                        fn (float $price, string $code) => CounterItem::sellableTodayInSize($product, ProductSize::from($code), $sellable, $data['sizeBroughtOut'], $data['soldBySize']),
                    )->all(),
                ];
            })->values(),
            'show_all' => $data['showAll'],
            'has_today_menu' => $data['hasTodayMenu'],
            'sales_count' => $data['salesCount'],
            'sales_total' => $data['salesTotal'],
            'recent_sales' => OrderResource::collection($data['recentSales']),
        ]);
    }

    public function receipt(Order $order): JsonResponse
    {
        $data = app(AdminPosController::class)->receipt($order)->getData();

        return response()->json(['order' => new OrderResource($data['order'])]);
    }

    /**
     * What is put out for sale at the counter today or tomorrow.
     */
    public function menu(Request $request): JsonResponse
    {
        $data = app(CounterMenuController::class)->edit($request)->getData();

        /** @var Collection<string, Collection<int, Product>> $groups */
        $groups = $data['products'];

        return response()->json([
            'date' => $data['date']->toDateString(),
            'day' => $data['day'],
            'groups' => $groups->map(fn (Collection $products, string $category) => [
                'category' => $category,
                'products' => ProductResource::collection($products),
            ])->values(),
            'selected' => $data['selected'],
            'quantities' => (object) $data['quantities']->all(),
            'size_quantities' => (object) $data['sizeQuantities']->all(),
            'sold_today' => (object) $data['soldToday']->all(),
            'previous_count' => $data['previousCount'],
        ]);
    }
}
