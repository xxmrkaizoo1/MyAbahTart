<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShopInsights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, ShopInsights $insights): View
    {
        $search = trim((string) $request->query('search', ''));
        $category = Category::query()->where('slug', $request->query('category'))->first();
        $stockLevel = $request->query('stock');

        $products = Product::query()
            ->with('category')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
            }))
            ->when($category, fn ($query) => $query->whereBelongsTo($category))
            ->when($stockLevel === 'out', fn ($query) => $query->where('stock', 0))
            ->when($stockLevel === 'low', fn ($query) => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->when($stockLevel === 'healthy', fn ($query) => $query->whereColumn('stock', '>', 'low_stock_threshold'))
            ->orderBy('name')
            ->get();

        $unitsSold = $insights->unitsSoldByProduct(7);

        $runningOut = Product::query()->available()->where('stock', '>', 0)->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'days_left' => ($unitsSold[$product->id] ?? 0) > 0 ? $product->stock / ($unitsSold[$product->id] / 7) : null,
            ])
            ->filter(fn (array $row) => $row['days_left'] !== null)
            ->sortBy('days_left')
            ->first();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'search' => $search,
            'stockLevel' => $stockLevel,
            'totalProducts' => Product::query()->count(),
            'newThisMonth' => Product::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'lowStockCount' => Product::query()->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'soldOutCount' => Product::query()->where('stock', 0)->count(),
            'runningOut' => $runningOut,
            'unitsSold' => $unitsSold,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true, 'low_stock_threshold' => 10, 'art' => 'pineapple']),
            'categories' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = new Product;
        $this->fillProduct($product, $request);

        return redirect()->route('admin.products.index')->with('status', "{$product->name} added to the menu.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->fillProduct($product, $request);

        return redirect()->route('admin.products.index')->with('status', "{$product->name} saved.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        Storage::disk('public')->delete($product->imagePaths());

        $product->delete();

        return redirect()->route('admin.products.index')->with('status', "{$product->name} deleted.");
    }

    public function updateStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $product->hasSizes()
            ? $request->validate([
                'size_stock' => ['required', 'array:'.implode(',', array_keys($product->sizePrices()))],
                'size_stock.*' => ['required', 'integer', 'min:0', 'max:100000'],
            ])
            : $request->validate([
                'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            ]);

        $product->update($validated);

        return back()->with('status', "{$product->name} stock set to {$product->stock}.");
    }

    public function restock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'size' => [$product->hasSizes() ? 'required' : 'prohibited', Rule::in(array_keys($product->sizePrices()))],
        ]);
        $size = ProductSize::tryFrom($validated['size'] ?? '');

        $product->adjustStock($size, $validated['quantity']);

        $received = $size === null ? $product->name : "{$product->name} ({$size->label()})";

        return back()->with('status', "Received {$validated['quantity']} × {$received}. Stock is now {$product->stockFor($size)}.");
    }

    private function fillProduct(Product $product, ProductRequest $request): void
    {
        $data = collect($request->validated())->except(['image', 'remove_image', 'size_images', 'remove_size_images'])->all();

        if (! empty($data['size_prices'])) {
            $data['size_prices'] = array_map(fn ($price) => round((float) $price, 2), $data['size_prices']);
            $data['price'] = min($data['size_prices']);
        }

        if ($product->name !== $data['name'] || ! $product->exists) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product);
        }

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store('products', 'public')
                : null;
        }

        $data['size_images'] = $this->sizeImages($product, $request, array_keys($data['size_prices'] ?? []));

        $product->fill($data)->save();
    }

    /**
     * Store, replace or remove each size's own photo. Photos for sizes no longer sold are deleted.
     *
     * @param  list<string>  $offeredSizes
     * @return array<string, string>|null
     */
    private function sizeImages(Product $product, ProductRequest $request, array $offeredSizes): ?array
    {
        $images = $product->size_images ?? [];

        foreach (ProductSize::cases() as $size) {
            $code = $size->value;
            $replace = $request->hasFile("size_images.{$code}");
            $remove = $request->boolean("remove_size_images.{$code}") || ! in_array($code, $offeredSizes, true);

            if (isset($images[$code]) && ($replace || $remove)) {
                Storage::disk('public')->delete($images[$code]);
                unset($images[$code]);
            }

            if ($replace && in_array($code, $offeredSizes, true)) {
                $images[$code] = $request->file("size_images.{$code}")->store('products', 'public');
            }
        }

        return $images === [] ? null : $images;
    }

    private function uniqueSlug(string $name, Product $product): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->whereKeyNot($product->id)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
