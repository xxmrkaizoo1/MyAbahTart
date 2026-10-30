<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CounterItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Choose which products are put out for sale at the counter each day.
 */
class CounterMenuController extends Controller
{
    public function edit(Request $request): View
    {
        $date = $this->dateFrom($request->query('day'));

        return view('admin.pos.menu', [
            'date' => $date,
            'day' => $date->isToday() ? 'today' : 'tomorrow',
            'products' => Product::query()->available()->with('category')->orderBy('name')->get()->groupBy('category.name'),
            'selected' => CounterItem::productIdsOn($date),
            'quantities' => CounterItem::quantitiesOn($date),
            'sizeQuantities' => CounterItem::sizeQuantitiesOn($date),
            'soldToday' => $date->isToday() ? CounterItem::soldOn($date) : collect(),
            'previousCount' => CounterItem::query()->forDate($date->copy()->subDay())->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'day' => ['required', Rule::in(['today', 'tomorrow'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('is_active', true)],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size_quantities' => ['nullable', 'array'],
            'size_quantities.*' => ['array:S,M,L'],
            'size_quantities.*.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'copy_previous' => ['boolean'],
        ], [
            'quantities.*.integer' => 'Quantities must be whole numbers.',
            'quantities.*.min' => 'Quantities can’t be negative.',
            'size_quantities.*.*.integer' => 'Quantities must be whole numbers.',
            'size_quantities.*.*.min' => 'Quantities can’t be negative.',
        ]);

        $date = $this->dateFrom($validated['day']);

        /** @var array<int, array{quantity: int|null, size_quantities: array<string, int|null>|null}> $plan Units to bring out, keyed by product id (null means no limit). */
        $plan = $request->boolean('copy_previous')
            ? CounterItem::query()->forDate($date->copy()->subDay())->get()
                ->mapWithKeys(fn (CounterItem $item) => [$item->product_id => ['quantity' => $item->quantity, 'size_quantities' => $item->size_quantities]])
                ->all()
            : $this->planFrom($validated);

        DB::transaction(function () use ($date, $plan) {
            CounterItem::query()->forDate($date)->whereNotIn('product_id', array_keys($plan))->delete();

            $existing = CounterItem::query()->forDate($date)->get()->keyBy('product_id');

            foreach ($plan as $productId => $amounts) {
                if ($existing->has($productId)) {
                    $existing[$productId]->update($amounts);
                } else {
                    CounterItem::create(['sale_date' => $date->toDateString(), 'product_id' => $productId, ...$amounts]);
                }
            }
        });

        $count = count($plan);
        $message = "{$count} ".Str::plural('item', $count)." set for the counter {$validated['day']}.";

        return $date->isToday() && ! $request->boolean('copy_previous')
            ? redirect()->route('admin.pos.index')->with('status', $message)
            : redirect()->route('admin.pos.menu', ['day' => $validated['day']])->with('status', $message);
    }

    /**
     * Turn the submitted form into amounts per product. Sized products take a number per size;
     * their total is only capped when every size has a number.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, array{quantity: int|null, size_quantities: array<string, int|null>|null}>
     */
    private function planFrom(array $validated): array
    {
        $toNumber = fn ($value) => $value === null || $value === '' ? null : (int) $value;
        $products = Product::query()->whereKey($validated['product_ids'] ?? [])->get()->keyBy('id');

        return $products->mapWithKeys(function (Product $product) use ($validated, $toNumber) {
            if (! $product->hasSizes()) {
                return [$product->id => ['quantity' => $toNumber($validated['quantities'][$product->id] ?? null), 'size_quantities' => null]];
            }

            $sizes = collect($product->sizePrices())->map(fn ($price, $code) => $toNumber($validated['size_quantities'][$product->id][$code] ?? null));

            return [$product->id => [
                'quantity' => $sizes->contains(null) ? null : $sizes->sum(),
                'size_quantities' => $sizes->filter(fn ($quantity) => $quantity !== null)->isEmpty() ? null : $sizes->all(),
            ]];
        })->all();
    }

    private function dateFrom(?string $day): Carbon
    {
        return $day === 'tomorrow' ? today()->addDay() : today();
    }
}
