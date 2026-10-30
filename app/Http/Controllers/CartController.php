<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Enums\ProductSize;
use App\Models\Product;
use App\Services\Cart;
use App\Services\Lalamove;
use App\Services\ShopInsights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, Cart $cart, ShopInsights $insights, Lalamove $lalamove): View
    {
        $deliveryMethods = DeliveryMethod::enabledCases();
        $deliveryMethod = DeliveryMethod::tryFrom((string) $request->old('delivery_method', $request->query('delivery', '')));
        $deliveryMethod = in_array($deliveryMethod, $deliveryMethods, true) ? $deliveryMethod : ($deliveryMethods[0] ?? DeliveryMethod::Pickup);

        $lines = $cart->lines();
        $inCart = $lines->pluck('product.id');

        $suggestions = $lines->isEmpty()
            ? $insights->popularProducts(4)
            : $lines->flatMap(fn (array $line) => $insights->boughtTogether($line['product'], 2))
                ->reject(fn (Product $product) => $inCart->contains($product->id))
                ->unique('id')
                ->take(3)
                ->values();

        return view('shop.cart', [
            'lines' => $lines,
            'deliveryMethod' => $deliveryMethod,
            'deliveryMethods' => $deliveryMethods,
            'liveQuoteMethod' => $lalamove->isLive() ? DeliveryMethod::Express : null,
            'summary' => $cart->summary($deliveryMethod),
            'suggestions' => $suggestions,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            ...$this->sizeRules($product),
        ]);
        $size = ProductSize::tryFrom($validated['size'] ?? '');

        if ($product->stockFor($size) < 1) {
            return back()->with('error', "Sorry, {$this->describe($product, $size)} is sold out.");
        }

        $quantity = $cart->add($product, (int) ($validated['quantity'] ?? 1), $size);

        if ($request->boolean('buy_now')) {
            return redirect()->route('cart.index');
        }

        return back()->with('status', "Added {$this->describe($product, $size)} to your cart ({$quantity}).");
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
            ...$this->sizeRules($product),
        ]);

        $size = ProductSize::tryFrom($validated['size'] ?? '');
        $quantity = $cart->put($product, (int) $validated['quantity'], $size);

        if ($quantity < (int) $validated['quantity']) {
            return back()->with('error', "Only {$product->stockFor($size)} of {$this->describe($product, $size)} left in stock.");
        }

        return back();
    }

    public function destroy(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $size = ProductSize::tryFrom((string) $request->input('size'));

        $cart->remove($product, $size);

        return back()->with('status', "Removed {$this->describe($product, $size)} from your cart.");
    }

    /**
     * Products sold in sizes need one of their sizes; other products take none.
     *
     * @return array<string, array<int, mixed>>
     */
    private function sizeRules(Product $product): array
    {
        return [
            'size' => $product->hasSizes()
                ? ['required', Rule::in(array_keys($product->sizePrices()))]
                : ['prohibited'],
        ];
    }

    private function describe(Product $product, ?ProductSize $size): string
    {
        return $size === null ? $product->name : "{$product->name} ({$size->label()})";
    }
}
