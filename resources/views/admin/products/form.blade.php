<x-layouts.admin :title="$product->exists ? 'Edit product' : 'New product'" :subtitle="$product->exists ? $product->name : 'Add a new product to the menu.'">
    <x-slot:actions>
        <a href="{{ route('admin.products.index') }}" class="btn-outline"><x-icon name="arrow-left" class="size-4" /> Products</a>
    </x-slot:actions>

    <form method="POST" enctype="multipart/form-data"
        action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
        class="grid gap-4 lg:grid-cols-[1fr_360px] lg:items-start">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <div class="space-y-4">
            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Details</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="label">Name</label>
                        <input id="name" name="name" value="{{ old('name', $product->name) }}" required class="input bg-paper">
                        @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="category_id" class="label">Collection</label>
                            <a href="{{ route('admin.categories.index') }}" class="text-xs font-bold text-berry-700 hover:underline">Manage collections</a>
                        </div>
                        <select id="category_id" name="category_id" required class="input bg-paper">
                            @if ($categories->isEmpty())
                                <option value="">Create a collection first</option>
                            @endif
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tagline" class="label">Tagline</label>
                        <input id="tagline" name="tagline" value="{{ old('tagline', $product->tagline) }}" placeholder="e.g. Slow-cooked pineapple jam" class="input bg-paper">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="description" class="label">Description</label>
                        <textarea id="description" name="description" rows="5" required class="input bg-paper">{{ old('description', $product->description) }}</textarea>
                        @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="card p-5 sm:p-7">
                <h2 class="font-display text-xl font-bold">Pricing & stock</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="price" class="label">Price (RM) <span class="font-normal text-ink-soft">· if no sizes</span></label>
                        <input id="price" name="price" type="number" step="0.01" min="0.5" value="{{ old('price', $product->hasSizes() ? null : $product->price) }}" class="input bg-paper">
                        @error('price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="unit_label" class="label">Sold as</label>
                        <input id="unit_label" name="unit_label" value="{{ old('unit_label', $product->unit_label) }}" required placeholder="Single, Box of 12…" class="input bg-paper">
                    </div>
                    <div>
                        <label for="stock" class="label">Stock <span data-size-stock-note class="hidden font-normal text-ink-soft">· total of all sizes</span></label>
                        <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', $product->stock ?? 0) }}" required data-size-stock-total class="input bg-paper read-only:cursor-not-allowed read-only:opacity-70">
                        @error('stock') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="low_stock_threshold" class="label">Low-stock alert at</label>
                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" required class="input bg-paper">
                    </div>
                    <div>
                        <label for="weight_grams" class="label">Weight (g) <span class="font-normal text-ink-soft">· optional</span></label>
                        <input id="weight_grams" name="weight_grams" type="number" min="1" max="100000" value="{{ old('weight_grams', $product->weight_grams) }}" placeholder="e.g. 300" class="input bg-paper">
                        @error('weight_grams') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <p class="self-end pb-3 text-xs text-ink-soft">Shown to customers next to “Sold as”, e.g. Box of 12 · 300 g. Leave blank to hide it.</p>
                </div>

                <fieldset data-size-stock-sizes class="mt-6 rounded-3xl bg-paper p-4 ring-1 ring-ink/5 sm:p-5">
                    <legend class="sr-only">Sizes</legend>
                    <p class="font-bold">Sizes <span class="text-sm font-normal text-ink-soft">(optional)</span></p>
                    <p class="mt-0.5 text-xs text-ink-soft">Enter a price for each size you sell. Leave a size blank if it isn’t offered. Each size has its own stock, and the total stock above is added up for you. Pieces and weight are optional: fill them in to show customers what comes in each size, or leave them blank to hide them.</p>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach (\App\Enums\ProductSize::cases() as $size)
                            <div>
                                <label for="size-{{ $size->value }}" class="label flex items-center gap-2">
                                    <span class="grid size-7 place-items-center rounded-lg bg-plum-900 text-xs font-extrabold text-white">{{ $size->value }}</span>
                                    {{ $size->label() }}
                                </label>
                                <span class="text-[11px] font-semibold text-ink-soft">Price (RM)</span>
                                <input id="size-{{ $size->value }}" name="size_prices[{{ $size->value }}]" type="number" step="0.01" min="0.5" data-size-price-input
                                    value="{{ old('size_prices.'.$size->value, $product->sizePrices()[$size->value] ?? null) }}" placeholder="Not offered" class="input bg-surface">
                                <label for="size-stock-{{ $size->value }}" class="mt-2 block text-[11px] font-semibold text-ink-soft">In stock</label>
                                <input id="size-stock-{{ $size->value }}" name="size_stock[{{ $size->value }}]" type="number" min="0" data-size-count
                                    value="{{ old('size_stock.'.$size->value, $product->sizeStock()[$size->value] ?? null) }}" placeholder="0" class="input bg-surface disabled:opacity-40">
                                <label for="size-pieces-{{ $size->value }}" class="mt-2 block text-[11px] font-semibold text-ink-soft">Pieces (pcs) <span class="font-normal">· optional</span></label>
                                <input id="size-pieces-{{ $size->value }}" name="size_pieces[{{ $size->value }}]" type="number" min="1" max="1000" data-size-extra
                                    value="{{ old('size_pieces.'.$size->value, $product->sizePieces()[$size->value] ?? null) }}" placeholder="e.g. 12" class="input bg-surface disabled:opacity-40">
                                <label for="size-grams-{{ $size->value }}" class="mt-2 block text-[11px] font-semibold text-ink-soft">Weight (g) <span class="font-normal">· optional</span></label>
                                <input id="size-grams-{{ $size->value }}" name="size_grams[{{ $size->value }}]" type="number" min="1" max="100000" data-size-extra
                                    value="{{ old('size_grams.'.$size->value, $product->sizeGrams()[$size->value] ?? null) }}" placeholder="e.g. 250" class="input bg-surface disabled:opacity-40">
                            </div>
                        @endforeach
                    </div>
                    @error('size_prices.*') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('size_stock.*') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('size_pieces.*') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('size_grams.*') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6">
            <section class="card p-5">
                <h2 class="font-display text-xl font-bold">Image</h2>
                <div id="image-preview" class="mt-4 aspect-square overflow-hidden rounded-3xl">
                    <x-product-image :product="$product" class="size-full" />
                </div>
                <label class="btn-outline mt-3 w-full cursor-pointer">
                    <x-icon name="upload" class="size-4" /> Upload photo
                    <input type="file" name="image" accept="image/*" class="sr-only" data-image-input="#image-preview">
                </label>
                @error('image') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                @if ($product->image_path)
                    <label class="mt-3 flex items-center gap-2 text-sm text-ink-soft">
                        <input type="checkbox" name="remove_image" value="1" class="rounded border-ink/20 text-berry-600"> Remove photo and use the default picture
                    </label>
                @endif

                <div class="mt-5 border-t border-ink/5 pt-4">
                    <p class="font-bold">Photo for each size <span class="text-sm font-normal text-ink-soft">(optional)</span></p>
                    <p class="mt-0.5 text-xs text-ink-soft">Shown when a customer picks that size. Sizes without one use the main photo.</p>
                    @error('size_images.*') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        @foreach (\App\Enums\ProductSize::cases() as $size)
                            @php($sizePhoto = $product->sizeImageUrl($size))
                            <div class="text-center">
                                <label class="group relative block cursor-pointer">
                                    <div id="size-photo-{{ $size->value }}" class="aspect-square overflow-hidden rounded-2xl bg-paper ring-1 ring-ink/10 transition group-hover:ring-berry-500">
                                        @if ($sizePhoto)
                                            <img src="{{ $sizePhoto }}" alt="" class="size-full object-cover">
                                        @else
                                            <span class="grid size-full place-items-center text-ink-soft"><x-icon name="upload" class="size-5" /></span>
                                        @endif
                                    </div>
                                    <span class="absolute top-1.5 left-1.5 grid size-6 place-items-center rounded-lg bg-plum-900 text-[11px] font-extrabold text-white">{{ $size->value }}</span>
                                    <input type="file" name="size_images[{{ $size->value }}]" accept="image/*" class="sr-only" data-image-input="#size-photo-{{ $size->value }}" aria-label="Photo for size {{ $size->label() }}">
                                </label>
                                @if ($sizePhoto)
                                    <label class="mt-1 inline-flex items-center gap-1 text-[11px] text-ink-soft">
                                        <input type="checkbox" name="remove_size_images[{{ $size->value }}]" value="1" class="size-3.5 rounded border-ink/20 text-berry-600"> Remove
                                    </label>
                                @else
                                    <span class="mt-1 block text-[11px] text-ink-soft">{{ $size->label() }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card space-y-1 p-3">
                @foreach ([
                    ['is_active', 'Visible on the menu', 'Customers can see and order it'],
                    ['is_bestseller', 'Bestseller badge', 'Highlight it on product cards'],
                    ['is_seasonal', 'Limited edition', 'Feature it on the home page'],
                ] as [$field, $label, $hint])
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl p-3 hover:bg-paper">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold">{{ $label }}</span>
                            <span class="block text-xs text-ink-soft">{{ $hint }}</span>
                        </span>
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $product->{$field})) class="peer sr-only">
                        <span class="relative h-7 w-12 shrink-0 rounded-full bg-ink/15 transition peer-checked:bg-mint-500 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                @endforeach
            </section>

            <button class="btn-primary w-full py-3.5 text-base">{{ $product->exists ? 'Save changes' : 'Add product' }}</button>
        </aside>
    </form>

    @if ($product->exists)
        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="mt-6" data-confirm="Delete {{ $product->name }}? This can’t be undone.">
            @csrf
            @method('DELETE')
            <button class="btn-ghost text-rose-700"><x-icon name="trash" class="size-4" /> Delete product</button>
        </form>
    @endif
</x-layouts.admin>
