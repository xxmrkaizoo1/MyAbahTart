<x-layouts.admin title="Counter items" :subtitle="'Choose what you’re selling at the counter on '.$date->format('l, j F').'.'">
    <x-slot:actions>
        <nav class="inline-flex rounded-full bg-surface p-1 ring-1 ring-ink/10" aria-label="Day">
            @foreach (['today' => 'Today', 'tomorrow' => 'Tomorrow'] as $value => $label)
                <a href="{{ route('admin.pos.menu', ['day' => $value]) }}" @class([
                    'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                    'bg-plum-900 text-white' => $day === $value,
                    'text-ink-soft hover:text-ink' => $day !== $value,
                ])>{{ $label }}</a>
            @endforeach
        </nav>
        <a href="{{ route('admin.pos.index') }}" class="btn-dark"><x-icon name="cart" class="size-4" /> Open counter</a>
    </x-slot:actions>

    @if ($products->isEmpty())
        <div class="card flex flex-col items-center px-6 py-16 text-center">
            <span class="grid size-16 place-items-center rounded-full bg-butter-200 text-plum-900"><x-icon name="box" class="size-7" /></span>
            <p class="mt-4 font-display text-xl font-bold">No products to sell yet</p>
            <p class="mt-1 max-w-sm text-sm text-ink-soft">Add products first, then choose which ones go on the counter each day.</p>
            <a href="{{ route('admin.products.create') }}" class="btn-primary mt-5"><x-icon name="plus" class="size-4" /> Add a product</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.pos.menu.update') }}" data-check-group>
            @csrf
            @method('PUT')
            <input type="hidden" name="day" value="{{ $day }}">

            <p class="mb-3 text-sm text-ink-soft">Tick what you’re selling, then enter how many you’re bringing to the counter. Leave <span class="font-semibold text-ink">Bring</span> empty to sell from the full stock.</p>
            @error('quantities.*') <p class="mb-3 rounded-2xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $message }}</p> @enderror

            <div class="card sticky top-3 z-20 flex flex-wrap items-center gap-2 p-3 sm:p-4">
                <p class="mr-auto text-sm"><span data-check-count class="font-display text-xl font-extrabold">{{ count($selected) }}</span> <span class="text-ink-soft">selected</span></p>
                <button type="button" data-check-all="on" class="btn-ghost py-2">Select all</button>
                <button type="button" data-check-all="off" class="btn-ghost py-2">Clear</button>
                @if ($previousCount > 0)
                    <button name="copy_previous" value="1" class="btn-outline py-2" data-confirm="Replace this list with the {{ $previousCount }} items from {{ $date->copy()->subDay()->isToday() ? 'today' : $date->copy()->subDay()->format('l') }}?">
                        <x-icon name="refresh" class="size-4" /> Copy {{ $date->copy()->subDay()->isToday() ? 'today' : ($date->copy()->subDay()->isYesterday() ? 'yesterday' : $date->copy()->subDay()->format('l')) }} ({{ $previousCount }})
                    </button>
                @endif
                <button class="btn-primary py-2"><x-icon name="check" class="size-4" /> Save</button>
            </div>

            @foreach ($products as $categoryName => $categoryProducts)
                <section class="mt-6">
                    <h2 class="font-display text-lg font-bold">{{ $categoryName }}</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach ($categoryProducts as $product)
                            @php($quantity = old('quantities.'.$product->id, $quantities[$product->id] ?? null))
                            <div class="relative flex flex-col overflow-hidden rounded-3xl bg-surface opacity-70 ring-1 ring-ink/10 transition hover:opacity-100 has-checked:opacity-100 has-checked:ring-2 has-checked:ring-berry-600 has-focus-visible:ring-2 has-focus-visible:ring-berry-500">
                                <label class="flex flex-1 cursor-pointer flex-col">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" data-quantity-box="{{ $product->id }}" class="peer sr-only" @checked(in_array($product->id, $selected, true))>
                                    <x-product-image :product="$product" class="aspect-[4/3]" />
                                    <span class="flex flex-1 flex-col p-3 pb-2">
                                        <span class="font-bold leading-tight">{{ $product->name }}</span>
                                        <span class="mt-1 text-xs text-ink-soft">
                                            @if ($product->hasSizes())
                                                {{ implode(' · ', array_map(fn ($code, $price) => $code.' RM'.number_format($price, 2), array_keys($product->sizePrices()), $product->sizePrices())) }}
                                            @else
                                                @rm($product->price)
                                            @endif
                                        </span>
                                        <span @class(['mt-auto pt-2 text-xs font-semibold', 'text-rose-700' => $product->isLowStock(), 'text-mint-700' => ! $product->isLowStock()])>
                                            {{ $product->isSoldOut() ? 'Sold out' : $product->stock.' in stock' }}
                                        </span>
                                    </span>
                                    <span class="absolute top-2.5 right-2.5 grid size-7 place-items-center rounded-full bg-surface text-transparent shadow-soft ring-1 ring-ink/10 transition peer-checked:bg-berry-600 peer-checked:text-white peer-checked:ring-berry-600">
                                        <x-icon name="check" class="size-4" />
                                    </span>
                                </label>
                                <div class="border-t border-ink/5 px-3 py-2.5">
                                    @if ($product->hasSizes())
                                        <p class="text-xs font-bold text-ink-soft">Bring <span class="font-normal">· blank = all</span></p>
                                        <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                                            @foreach ($product->sizeStock() as $code => $inStock)
                                                @php($sizeQuantity = old("size_quantities.{$product->id}.{$code}", $sizeQuantities[$product->id][$code] ?? null))
                                                <label class="block text-center">
                                                    <span @class(['block text-[11px] font-extrabold', 'text-rose-700' => $inStock === 0, 'text-ink' => $inStock > 0])>{{ $code }} <span class="font-semibold text-ink-soft">/ {{ $inStock }}</span></span>
                                                    <input name="size_quantities[{{ $product->id }}][{{ $code }}]" type="number" min="0" max="100000" inputmode="numeric"
                                                        value="{{ $sizeQuantity }}" placeholder="All" data-quantity-for="{{ $product->id }}" aria-label="Bring {{ \App\Enums\ProductSize::from($code)->label() }} {{ $product->name }}"
                                                        @class(['input mt-0.5 bg-paper px-1 py-1.5 text-center text-sm font-bold', 'ring-2 ring-rose-400' => $sizeQuantity !== null && $sizeQuantity !== '' && (int) $sizeQuantity > $inStock])>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <label for="quantity-{{ $product->id }}" class="text-xs font-bold text-ink-soft">Bring</label>
                                            <input id="quantity-{{ $product->id }}" name="quantities[{{ $product->id }}]" type="number" min="0" max="100000" inputmode="numeric"
                                                value="{{ $quantity }}" placeholder="All" data-quantity-for="{{ $product->id }}" class="input min-w-0 flex-1 bg-paper px-3 py-1.5 text-sm font-bold">
                                        </div>
                                        @if ($quantity !== null && $quantity !== '' && (int) $quantity > $product->stock)
                                            <p class="mt-1 text-[11px] font-semibold text-rose-700">More than the {{ $product->stock }} in stock</p>
                                        @endif
                                    @endif
                                    @if ($day === 'today' && ($soldToday[$product->id] ?? 0) > 0)
                                        <p class="mt-1 text-[11px] font-semibold text-mint-700">{{ $soldToday[$product->id] }} sold at the counter today</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </form>
    @endif
</x-layouts.admin>
