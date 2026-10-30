<x-layouts.admin title="Products" subtitle="Your menu, prices and today’s stock at a glance.">
    <x-slot:actions>
        <a href="{{ route('admin.products.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New product</a>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="card p-5">
            <p class="text-sm font-semibold text-ink-soft">Products</p>
            <p class="mt-1 font-display text-3xl font-extrabold">{{ $totalProducts }}</p>
            <p class="mt-1 text-xs font-semibold text-mint-600">+{{ $newThisMonth }} this month</p>
        </div>
        <a href="{{ route('admin.products.index', ['stock' => 'low']) }}" class="card block p-5 transition hover:-translate-y-0.5">
            <p class="text-sm font-semibold text-ink-soft">Running low</p>
            <p class="mt-1 font-display text-3xl font-extrabold text-butter-600">{{ $lowStockCount - $soldOutCount }}</p>
            <p class="mt-1 text-xs text-ink-soft">At or under threshold</p>
        </a>
        <a href="{{ route('admin.products.index', ['stock' => 'out']) }}" class="card block p-5 transition hover:-translate-y-0.5">
            <p class="text-sm font-semibold text-ink-soft">Sold out</p>
            <p @class(['mt-1 font-display text-3xl font-extrabold', 'text-rose-600' => $soldOutCount > 0])>{{ $soldOutCount }}</p>
            <p class="mt-1 text-xs text-ink-soft">Hidden from checkout</p>
        </a>
        <div class="col-span-2 rounded-3xl bg-plum-900 p-5 text-white xl:col-span-1">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-butter-300"><x-icon name="sparkles" class="size-4" /> Demand forecast</p>
            @if ($runningOut)
                <p class="mt-2 text-sm leading-relaxed text-white/80">
                    <span class="font-bold text-white">{{ $runningOut['product']->name }}</span> will sell out in
                    <span class="font-bold text-butter-300">{{ $runningOut['days_left'] < 1 ? 'under a day' : round($runningOut['days_left']).' '.Str::plural('day', round($runningOut['days_left'])) }}</span>
                    at the current pace.
                </p>
            @else
                <p class="mt-2 text-sm text-white/70">Not enough sales data yet.</p>
            @endif
        </div>
    </div>

    <form class="mt-6 flex flex-col gap-2 sm:flex-row">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
            <input type="search" name="search" value="{{ $search }}" placeholder="Search products" class="input rounded-full pl-10" aria-label="Search products">
        </div>
        <select name="category" data-autosubmit class="input rounded-full sm:w-52" aria-label="Collection">
            <option value="">All collections</option>
            @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected($activeCategory?->is($category))>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="stock" data-autosubmit class="input rounded-full sm:w-44" aria-label="Stock level">
            <option value="">Any stock level</option>
            <option value="low" @selected($stockLevel === 'low')>Running low</option>
            <option value="out" @selected($stockLevel === 'out')>Sold out</option>
            <option value="healthy" @selected($stockLevel === 'healthy')>Healthy</option>
        </select>
    </form>

    @if ($products->isEmpty())
        <p class="card mt-6 p-12 text-center text-sm text-ink-soft">No products match these filters.</p>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $product)
                <article class="card flex flex-col overflow-hidden">
                    <div class="flex gap-4 p-4">
                        <x-product-image :product="$product" class="size-20 shrink-0 rounded-2xl" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.products.edit', $product) }}" class="font-display text-lg leading-tight font-bold hover:text-berry-700">{{ $product->name }}</a>
                                <a href="{{ route('admin.products.edit', $product) }}" class="grid size-8 shrink-0 place-items-center rounded-full text-ink-soft hover:bg-paper hover:text-ink" aria-label="Edit {{ $product->name }}"><x-icon name="edit" class="size-4" /></a>
                            </div>
                            <p class="text-xs text-ink-soft">{{ $product->category->name }} · {{ $product->hasSizes() ? 'From ' : '' }}@rm($product->price){{ $product->hasSizes() ? ' ('.implode(' / ', array_keys($product->sizePrices())).')' : '' }} / {{ $product->unit_label }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @unless ($product->is_active)
                                    <span class="chip bg-ink/5 text-ink-soft">Hidden</span>
                                @endunless
                                @if ($product->is_bestseller)
                                    <span class="chip bg-butter-200 text-plum-900">Bestseller</span>
                                @endif
                                @if ($product->is_seasonal)
                                    <span class="chip bg-plum-900 text-white">Limited</span>
                                @endif
                                <span class="chip bg-paper text-ink-soft">{{ $unitsSold[$product->id] ?? 0 }} sold / 7d</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-ink/5 bg-paper/60 p-4">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span @class([
                                'text-rose-600' => $product->isSoldOut(),
                                'text-butter-600' => ! $product->isSoldOut() && $product->isLowStock(),
                                'text-mint-600' => ! $product->isLowStock(),
                            ])>{{ $product->isSoldOut() ? 'Sold out' : ($product->isLowStock() ? 'Running low' : 'Healthy') }}</span>
                            <span class="text-ink-soft">{{ $product->stock }} in stock</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-ink/5">
                            <div @class([
                                'h-full rounded-full',
                                'bg-rose-500' => $product->isSoldOut(),
                                'bg-butter-400' => ! $product->isSoldOut() && $product->isLowStock(),
                                'bg-mint-500' => ! $product->isLowStock(),
                            ]) style="width: {{ max(3, $product->stockPercentage()) }}%"></div>
                        </div>
                        <form method="POST" action="{{ route('admin.products.stock', $product) }}" class="mt-3 flex items-end gap-2">
                            @csrf
                            @method('PATCH')
                            @if ($product->hasSizes())
                                @foreach ($product->sizeStock() as $code => $count)
                                    <label class="min-w-0 flex-1">
                                        <span @class(['block text-center text-[11px] font-bold', 'text-rose-600' => $count === 0, 'text-ink-soft' => $count > 0])>{{ $code }}</span>
                                        <input type="number" name="size_stock[{{ $code }}]" value="{{ $count }}" min="0" class="input rounded-full px-2 py-2 text-center" aria-label="{{ \App\Enums\ProductSize::from($code)->label() }} stock for {{ $product->name }}">
                                    </label>
                                @endforeach
                            @else
                                <input type="number" name="stock" value="{{ $product->stock }}" min="0" class="input rounded-full py-2 text-center" aria-label="Stock for {{ $product->name }}">
                            @endif
                            <button class="btn-dark shrink-0 py-2">Update</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
