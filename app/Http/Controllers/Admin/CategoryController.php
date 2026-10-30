<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request, new Category);
        $validated['sort_order'] ??= (int) Category::query()->max('sort_order') + 1;
        $validated['slug'] = $this->uniqueSlug($validated['name'], new Category);

        Category::create($this->withImage($validated, $request, new Category));

        return back()->with('status', "Collection {$validated['name']} created.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category, 'category'.$category->id);
        $validated['sort_order'] ??= $category->sort_order;

        if ($validated['name'] !== $category->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $category);
        }

        $category->update($this->withImage($validated, $request, $category));

        return back()->with('status', "Collection {$category->name} updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            return back()->with('error', "Move or delete the {$productCount} ".Str::plural('product', $productCount)." in {$category->name} first.");
        }

        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();

        return back()->with('status', "Collection {$category->name} deleted.");
    }

    /**
     * Validate a collection; each edit form reports its errors in its own bag.
     *
     * @return array{name: string, sort_order: int|null, image?: mixed, remove_image?: bool}
     */
    private function validateCategory(Request $request, Category $category, string $errorBag = 'default'): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validateWithBag($errorBag, [
            'name' => ['required', 'string', 'max:60', Rule::unique('categories', 'name')->ignore($category)],
            'image' => ['nullable', 'image', 'max:10240'],
            'remove_image' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /**
     * Store a newly uploaded photo, or drop the old one when it is removed or replaced.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withImage(array $validated, Request $request, Category $category): array
    {
        unset($validated['image'], $validated['remove_image']);

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }

            $validated['image_path'] = $request->hasFile('image') ? $request->file('image')->store('collections', 'public') : null;
        }

        return $validated;
    }

    private function uniqueSlug(string $name, Category $category): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
